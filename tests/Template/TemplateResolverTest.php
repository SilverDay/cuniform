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

    public function testRejectsATemplateNameNotOnTheAllowList(): void
    {
        $resolver = new TemplateResolver('/srv/templates');

        $this->expectException(RenderException::class);
        $resolver->resolve('index.php');
    }

    public function testRejectsAPathTraversalAttempt(): void
    {
        $resolver = new TemplateResolver('/srv/templates');

        $this->expectException(RenderException::class);
        $resolver->resolve('../../../../etc/passwd');
    }
}
