<?php

declare(strict_types=1);

namespace FreshetFeeds\Template;

/**
 * WooCommerce-style template resolution:
 *   {child theme}/freshet-feeds/{name}.php
 *   {parent theme}/freshet-feeds/{name}.php
 *   {plugin}/templates/{name}.php
 */
final class TemplateLoader
{
    public function __construct(
        private readonly string $pluginTemplateDir,
        private readonly string $themeSubdir = 'freshet-feeds',
    ) {
    }

    /** Absolute path of the winning template file, or null if the name is unknown everywhere. */
    public function locate(string $name): ?string
    {
        $name = str_replace(['..', "\0"], '', $name);
        $file = $name . '.php';

        /**
         * Filter the theme subdirectory searched for template overrides.
         *
         * @param string $subdir Default 'freshet-feeds'.
         */
        $subdir = (string) apply_filters('freshet_feeds_template_path', $this->themeSubdir);

        $located = locate_template([trailingslashit($subdir) . $file]);

        if ($located === '') {
            $fallback = trailingslashit($this->pluginTemplateDir) . $file;
            $located = is_readable($fallback) ? $fallback : '';
        }

        /**
         * Filter the final resolved template path.
         *
         * @param string $located Absolute path ('' if not found).
         * @param string $name    Template name without extension.
         */
        $located = (string) apply_filters('freshet_feeds_template', $located, $name);

        return $located !== '' && is_readable($located) ? $located : null;
    }

    /**
     * First template that resolves anywhere in the chain, in order of
     * preference — the item hierarchy (item-{feed} → item-{provider} → item)
     * runs through this.
     *
     * @param list<string> $names
     */
    public function locateFirst(array $names): ?string
    {
        foreach ($names as $name) {
            $located = $this->locate($name);

            if ($located !== null) {
                return $located;
            }
        }

        return null;
    }

    /**
     * Render the first template of $names that exists.
     *
     * @param list<string> $names
     * @param array<string, mixed> $vars
     */
    public function renderFirst(array $names, array $vars = []): void
    {
        $template = $this->locateFirst($names);

        if ($template === null) {
            return;
        }

        (static function (string $__template, array $__vars): void {
            extract($__vars, EXTR_SKIP); // phpcs:ignore WordPress.PHP.DontExtract
            include $__template;
        })($template, $vars);
    }

    /**
     * Render a template with $vars extracted into scope.
     *
     * @param array<string, mixed> $vars
     */
    public function render(string $name, array $vars = []): void
    {
        $template = $this->locate($name);

        if ($template === null) {
            if (defined('WP_DEBUG') && WP_DEBUG) {
                trigger_error(
                    sprintf('Freshet Feeds: template "%s" not found.', esc_html($name)),
                    E_USER_NOTICE
                );
            }

            return;
        }

        (static function (string $__template, array $__vars): void {
            extract($__vars, EXTR_SKIP); // phpcs:ignore WordPress.PHP.DontExtract
            include $__template;
        })($template, $vars);
    }
}
