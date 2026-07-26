<?php

declare(strict_types=1);

namespace FreshetFeeds\Tests\Unit;

use Brain\Monkey\Functions;
use FreshetFeeds\Template\TemplateLoader;

final class TemplateLoaderTest extends TestCase
{
    private string $pluginDir;
    private string $themeDir;

    protected function setUp(): void
    {
        parent::setUp();

        $base = sys_get_temp_dir() . '/freshet-feeds-test-' . uniqid();
        $this->pluginDir = $base . '/plugin-templates';
        $this->themeDir = $base . '/theme/freshet-feeds';
        mkdir($this->pluginDir, 0777, true);
        mkdir($this->themeDir, 0777, true);

        Functions\when('trailingslashit')->alias(static fn (string $s): string => rtrim($s, '/') . '/');
        Functions\when('apply_filters')->alias(static fn (string $hook, mixed $value): mixed => $value);
    }

    public function testFallsBackToPluginTemplate(): void
    {
        file_put_contents($this->pluginDir . '/item.php', '<?php echo "plugin";');
        Functions\when('locate_template')->justReturn('');

        $loader = new TemplateLoader($this->pluginDir);

        $this->assertSame($this->pluginDir . '/item.php', $loader->locate('item'));
    }

    public function testThemeOverrideWins(): void
    {
        file_put_contents($this->pluginDir . '/item.php', '<?php echo "plugin";');
        $override = $this->themeDir . '/item.php';
        file_put_contents($override, '<?php echo "theme";');

        // locate_template() is WP's child→parent resolution; simulate a theme hit.
        Functions\when('locate_template')->alias(fn (array $names): string => $override);

        $loader = new TemplateLoader($this->pluginDir);

        $this->assertSame($override, $loader->locate('item'));
    }

    public function testUnknownTemplateReturnsNull(): void
    {
        Functions\when('locate_template')->justReturn('');

        $loader = new TemplateLoader($this->pluginDir);

        $this->assertNull($loader->locate('nope'));
    }

    public function testLocateStripsTraversal(): void
    {
        Functions\when('locate_template')->justReturn('');
        file_put_contents($this->pluginDir . '/secret.php', '<?php echo "x";');

        $loader = new TemplateLoader($this->pluginDir);

        // "../" is stripped, so this resolves inside the template dir or not at all.
        $this->assertNull($loader->locate('../secret-elsewhere'));
    }

    public function testLocateFirstFollowsHierarchyOrder(): void
    {
        Functions\when('locate_template')->justReturn('');
        file_put_contents($this->pluginDir . '/item-youtube.php', '<?php echo "provider";');
        file_put_contents($this->pluginDir . '/item.php', '<?php echo "generic";');

        $loader = new TemplateLoader($this->pluginDir);

        // Most specific name that exists wins; missing names are skipped.
        $this->assertSame(
            $this->pluginDir . '/item-youtube.php',
            $loader->locateFirst(['item-my-feed', 'item-youtube', 'item'])
        );
        $this->assertSame(
            $this->pluginDir . '/item.php',
            $loader->locateFirst(['item-my-feed', 'item-linkedin', 'item'])
        );
        $this->assertNull($loader->locateFirst(['nope-a', 'nope-b']));
    }

    public function testRenderFirstRendersWinningTemplate(): void
    {
        Functions\when('locate_template')->justReturn('');
        file_put_contents($this->pluginDir . '/item-youtube.php', '<?php echo "video:" . $word;');

        $loader = new TemplateLoader($this->pluginDir);

        ob_start();
        $loader->renderFirst(['item-my-feed', 'item-youtube', 'item'], ['word' => 'yes']);

        $this->assertSame('video:yes', ob_get_clean());
    }

    public function testRenderExtractsVars(): void
    {
        file_put_contents($this->pluginDir . '/echo.php', '<?php echo strtoupper($word);');
        Functions\when('locate_template')->justReturn('');

        $loader = new TemplateLoader($this->pluginDir);

        ob_start();
        $loader->render('echo', ['word' => 'hi']);

        $this->assertSame('HI', ob_get_clean());
    }
}
