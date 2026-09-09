<?php

declare(strict_types=1);

namespace Cuniform\Import;

/**
 * Reads a WXR export (SPEC §A.1) with `XMLReader` in streaming mode —
 * memory stays bounded to one `<item>` at a time regardless of export
 * size, never a single DOMDocument for the whole file. External entity
 * loading is disabled via `LIBXML_NONET` (blocks network-borne entity/DTD
 * resolution — an explicit statement of intent, not reliance on the fact
 * that modern libxml2 already disables external entity *substitution* by
 * default; verified empirically against a crafted XXE payload before
 * committing to this design — see WxrReaderTest).
 *
 * Implementation: `XMLReader` walks the document at depth 2 (direct
 * children of `<channel>`) looking for `<item>` and `<wp:author>`
 * elements and the handful of scalar channel fields; each matched
 * element's `readOuterXML()` is handed to `simplexml_load_string()` for
 * convenient namespaced field access. This is safe specifically because
 * `readOuterXML()` re-serializes every namespace declaration an element's
 * descendants need onto that element itself (confirmed empirically, not
 * assumed) — a `<item>` fragment parses standalone even though its `wp:`/
 * `content:`/`excerpt:`/`dc:` prefixes were declared on the document's
 * root `<rss>`, not on the item itself.
 *
 * A malformed item is NOT caught and skipped independently of the rest —
 * confirmed empirically (not assumed) that `XMLReader::read()` itself
 * fails at the very first well-formedness problem in the *whole*
 * document, however far into it, since the reader tokenizes forward from
 * the start; there is no such thing as "one bad item, N-1 good ones" for
 * a streaming reader the way there is for N independent front-matter
 * files (`DocumentParser`, SPEC §5.5's per-document error collection).
 * A parse failure here always means the file itself, not one entry in
 * it, and this class treats it that way: the first error aborts the
 * whole read, reported as a single ImportException.
 */
final class WxrReader
{
    private const CHANNEL_FIELDS      = ['title', 'link', 'description', 'language'];
    private const CHANNEL_WP_FIELDS   = ['wxr_version', 'base_site_url', 'base_blog_url'];

    /**
     * @throws ImportException When the file can't be opened, or the
     *                         document (or any single `<item>`/
     *                         `<wp:author>` within it) fails to parse.
     */
    public function parse(string $path): WxrDocument
    {
        if (!is_file($path) || !is_readable($path)) {
            throw ImportException::fileNotReadable($path);
        }

        $previousSetting = libxml_use_internal_errors(true);
        libxml_clear_errors();

        try {
            $reader = new \XMLReader();
            if (!$reader->open($path, null, \LIBXML_NONET)) {
                throw ImportException::fileNotReadable($path);
            }

            try {
                return $this->readDocument($reader);
            } finally {
                $reader->close();
            }
        } finally {
            libxml_use_internal_errors($previousSetting);
        }
    }

    private function readDocument(\XMLReader $reader): WxrDocument
    {
        /** @var array<string, string> $channelFields */
        $channelFields = [];
        /** @var list<WxrAuthor> $authors */
        $authors = [];
        /** @var list<WxrItem> $items */
        $items = [];

        while ($reader->read()) {
            if ($reader->nodeType !== \XMLReader::ELEMENT || $reader->depth !== 2) {
                continue;
            }

            $localName = $reader->localName;
            $prefix    = $reader->prefix;

            if ($prefix === '' && $localName === 'item') {
                $items[] = $this->parseItem($reader->readOuterXML());

                continue;
            }

            if ($prefix === 'wp' && $localName === 'author') {
                $authors[] = $this->parseAuthor($reader->readOuterXML());

                continue;
            }

            if ($prefix === '' && in_array($localName, self::CHANNEL_FIELDS, true)) {
                $channelFields[$localName] = $reader->readString();

                continue;
            }

            if ($prefix === 'wp' && in_array($localName, self::CHANNEL_WP_FIELDS, true)) {
                $channelFields[$localName] = $reader->readString();
            }
        }

        if (libxml_get_errors() !== []) {
            throw ImportException::malformedXml($this->firstLibxmlError());
        }

        return new WxrDocument($this->buildChannel($channelFields, $authors), $items);
    }

    /**
     * @param array<string, string> $fields
     * @param list<WxrAuthor>       $authors
     */
    private function buildChannel(array $fields, array $authors): WxrChannel
    {
        return new WxrChannel(
            title: $fields['title'] ?? '',
            link: $fields['link'] ?? '',
            description: $fields['description'] ?? '',
            language: $fields['language'] ?? '',
            wxrVersion: $fields['wxr_version'] ?? '',
            baseSiteUrl: $fields['base_site_url'] ?? '',
            baseBlogUrl: $fields['base_blog_url'] ?? '',
            authors: $authors,
        );
    }

    /**
     * @throws ImportException
     */
    private function parseItem(string $outerXml): WxrItem
    {
        $simple = simplexml_load_string($outerXml);
        if ($simple === false) {
            throw ImportException::malformedXml('an <item> failed to parse: ' . $this->firstLibxmlError());
        }

        $wp      = $simple->children('wp', true);
        $content = $simple->children('content', true);
        $excerpt = $simple->children('excerpt', true);
        $dc      = $simple->children('dc', true);

        $attachmentUrl = trim((string) $wp->attachment_url);

        return new WxrItem(
            title: trim((string) $simple->title),
            link: trim((string) $simple->link),
            pubDate: $this->parsePubDate((string) $simple->pubDate),
            creator: trim((string) $dc->creator),
            guid: trim((string) $simple->guid),
            description: (string) $simple->description,
            contentEncoded: (string) $content->encoded,
            excerptEncoded: (string) $excerpt->encoded,
            postId: (int) $wp->post_id,
            postDate: trim((string) $wp->post_date),
            postDateGmt: $this->parseGmtDate((string) $wp->post_date_gmt),
            postModified: trim((string) $wp->post_modified),
            postModifiedGmt: $this->parseGmtDate((string) $wp->post_modified_gmt),
            commentStatus: trim((string) $wp->comment_status),
            pingStatus: trim((string) $wp->ping_status),
            postName: trim((string) $wp->post_name),
            status: trim((string) $wp->status),
            postParent: (int) $wp->post_parent,
            menuOrder: (int) $wp->menu_order,
            postType: trim((string) $wp->post_type),
            postPassword: (string) $wp->post_password,
            isSticky: trim((string) $wp->is_sticky) === '1',
            attachmentUrl: $attachmentUrl === '' ? null : $attachmentUrl,
            categories: $this->parseCategories($simple),
            postmeta: $this->parsePostmeta($wp),
        );
    }

    /**
     * @throws ImportException
     */
    private function parseAuthor(string $outerXml): WxrAuthor
    {
        $simple = simplexml_load_string($outerXml);
        if ($simple === false) {
            throw ImportException::malformedXml('a <wp:author> failed to parse: ' . $this->firstLibxmlError());
        }

        $wp = $simple->children('wp', true);

        return new WxrAuthor(
            id: (int) $wp->author_id,
            login: trim((string) $wp->author_login),
            email: trim((string) $wp->author_email),
            displayName: trim((string) $wp->author_display_name),
            firstName: trim((string) $wp->author_first_name),
            lastName: trim((string) $wp->author_last_name),
        );
    }

    /**
     * @return list<WxrCategory>
     */
    private function parseCategories(\SimpleXMLElement $item): array
    {
        $categories = [];
        foreach ($item->category as $category) {
            $attributes   = $category->attributes();
            $categories[] = new WxrCategory(
                (string) ($attributes->domain ?? ''),
                (string) ($attributes->nicename ?? ''),
                trim((string) $category),
            );
        }

        return $categories;
    }

    /**
     * @return array<string, list<string>>
     */
    private function parsePostmeta(\SimpleXMLElement $wp): array
    {
        $postmeta = [];
        foreach ($wp->postmeta as $meta) {
            $key = (string) $meta->meta_key;
            $postmeta[$key][] = (string) $meta->meta_value;
        }

        return $postmeta;
    }

    private function parseGmtDate(string $raw): ?\DateTimeImmutable
    {
        $raw = trim($raw);
        if ($raw === '' || $raw === '0000-00-00 00:00:00') {
            return null;
        }

        $date = \DateTimeImmutable::createFromFormat('Y-m-d H:i:s', $raw, new \DateTimeZone('UTC'));

        return $date === false ? null : $date;
    }

    private function parsePubDate(string $raw): ?\DateTimeImmutable
    {
        $raw = trim($raw);
        if ($raw === '') {
            return null;
        }

        try {
            return new \DateTimeImmutable($raw);
        } catch (\Exception) {
            return null;
        }
    }

    /**
     * Reads (and clears) the current libxml error buffer — shared global
     * state across every libxml-backed call in this class (XMLReader and
     * simplexml_load_string alike), so each call site that consults it
     * must also clear it, or a later check would re-report an error an
     * earlier catch block already handled.
     */
    private function firstLibxmlError(): string
    {
        $errors = libxml_get_errors();
        libxml_clear_errors();

        return $errors === [] ? 'unknown error' : trim($errors[0]->message);
    }
}
