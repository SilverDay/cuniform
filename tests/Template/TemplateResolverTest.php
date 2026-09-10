<?php

declare(strict_types=1);

namespace Cuniform\Tests\Template;

use Cuniform\Render\RenderException;
use Cuniform\Template\TemplateResolver;
use PHPUnit\Framework\TestCase;

final class TemplateResolverTest extends TestCase
{
    public function testResolvesAnAllowListedTemplate(): void
    {
        $resolver = new TemplateResolver('/srv/templates');

        self::assertSame('/srv/templates/post.php', $resolver->resolve('post.php'));
    }

    public function testPrefersConfiguredTemplateSetBeforeDefaultTemplateFallback(): void
    {
        $templatesDir = sys_get_temp_dir() . '/cuniform_tplset_' . uniqid('', true);
        mkdir($templatesDir . '/custom', 0o755, true);
        file_put_contents($templatesDir . '/custom/layout.php', '<?php echo "custom";');
        file_put_contents($templatesDir . '/page.php', '<?php echo "default";');

        $resolver = new TemplateResolver($templatesDir, 'custom');

        self::assertSame($templatesDir . '/custom/layout.php', $resolver->resolve('layout.php'));
        self::assertSame($templatesDir . '/page.php', $resolver->resolve('page.php'));
    }

    public function testResolvesPartialWithTemplateSetAndDefaultFallback(): void
    {
        $templatesDir = sys_get_temp_dir() . '/cuniform_tplpart_' . uniqid('', true);
        mkdir($templatesDir . '/custom/partials', 0o755, true);
        mkdir($templatesDir . '/partials', 0o755, true);
        file_put_contents($templatesDir . '/custom/partials/head.php', '<!-- custom head -->');
        file_put_contents($templatesDir . '/partials/post-card.php', '<!-- default post card -->');

        $resolver = new TemplateResolver($templatesDir, 'custom');

        self::assertSame($templatesDir . '/custom/partials/head.php', $resolver->resolvePartial('head.php'));
        self::assertSame($templatesDir . '/custom/partials/head.php', $resolver->resolvePartial('partials/head.php'));
        self::assertSame($templatesDir . '/partials/post-card.php', $resolver->resolvePartial('post-card.php'));
    }

    public function testRejectsAPartialNotOnTheAllowList(): void
    {
        $resolver = new TemplateResolver('/srv/templates');

        $this->expectException(RenderException::class);
        $resolver->resolvePartial('secret.php');
    }

    public function testRejectsATemplateNameNotOnTheAllowList(): void
    {
        $resolver = new TemplateResolver('/srv/templates');

        // Deliberately absent — see TemplateResolver's own docblock: feeds
        // are built directly by FeedGenerator, never through a template.
        $this->expectException(RenderException::class);
        $resolver->resolve('feed.xml.php');
    }

    public function testRejectsAPathTraversalAttempt(): void
    {
        $resolver = new TemplateResolver('/srv/templates');

        $this->expectException(RenderException::class);
        $resolver->resolve('../../../../etc/passwd');
    }
}
