<?php

namespace AvosKitchen\Sanitizer;

use Parsedown;
use HTMLPurifier;
use HTMLPurifier_Config;
use HTMLPurifier_DefinitionCacheFactory;
use Kirby\Toolkit\Str;

class Sanitizer
{
    /**
     * Cached instance of HTML Purifier instance used for processing
     *
     * @var \HTMLPurifier
     */
    protected static $purifier;

    /**
     * Cached instance of the Parsedown Markdown parser
     *
     * @var \Parsedown
     */
    protected static $parsedown;

    /**
     * Subset of HTML inline elements needed for sanitization
     *
     * @var array
     */
    const INLINE_ELEMENTS = [
        'a',
        'abbr',
        'b',
        'br',
        'cite',
        'code',
        'del',
        'em',
        'i',
        'ins',
        'kbd',
        'mark',
        'q',
        'strong',
        'sub',
        'sup',
    ];

    /**
     * Subset of HTML block elements needed for sanitization
     *
     * @var array
     */
    const BLOCK_ELEMENTS = [
        'blockquote',
        'li',
        'ol',
        'p',
        'pre',
        'ul',
        'h1',
        'h2',
        'h3',
        'h4',
        'h5',
        'h6',
    ];

    /**
     * Generates the config string for HTML Purifier’s list of allowed
     * elements, based on the plugin configutation
     *
     * @param array $options Configuration
     * @return string The configuration string for HTML Purifier
     */
    protected static function getAllowedElements(array $options): string
    {
        $allowed = [
            '*[lang|dir]',
            'abbr[title]',
            'b',
            'blockquote',
            'br',
            'cite',
            'code[class]',
            'del',
            'em',
            'h1',
            'h2',
            'h3',
            'h4',
            'h5',
            'h6',
            'i',
            'ins',
            'kbd',
            'li',
            'mark',
            'ol',
            'p',
            'pre[class]',
            'q',
            'strong',
            'sub',
            'sup',
            'ul',
        ];

        if ($options['allowlinks'] === true) {
            $allowed[] = 'a[rel|href]';
        }

        return implode(',', $allowed);
    }

    /**
     * Converts untrusted HTML/Markdown input into sanitized, safe HTML code.
     *
     * @param string $text The input text, expecting "dirty" HTML code and/or Markdown
     * @param array $options Configuration
     * @return string The cleaned-up/"purified" text.
     */
    public static function sanitize(string $text, array $options = []): string
    {
        $options = array_merge([
            'dir' => option('avoskitchen.sanitizer.dir', null),
            'markdown' => option('avoskitchen.sanitizer.markdown', false),
            'smartypants' => option('smartypants', false),
            'allowlinks' => option('avoskitchen.sanitizer.allowlinks', true),
            'autolinks' => option('avoskitchen.sanitizer.autolinks', true),
            'headingClass' => option('avoskitchen.sanitizer.headingClass'),
        ], $options);

        if ($options['markdown'] === true) {
            $text = static::markdown($text, $options);
        }

        if ($options['smartypants'] === true) {
            // Only apply smartypants filter, if enabled in Kirby
            $text = smartypants($text);
        }

        return static::purifiy($text, $options);
    }

    /**
     * Cleans up a string of dirty HTML from invalid syntax, malicious
     * code and strips all tags and attributes, except for a few from
     * a given whitelist.
     *
     * @param string $text Untrusted string of HTML
     * @param array $options Configuration
     * @return string Sanitized HTML string
     */
    protected static function purifiy(string $text, array $options): string
    {
        if (static::$purifier === null) {

            $purifierCache = HTMLPurifier_DefinitionCacheFactory::instance();
            // Workaround for force-loading the class, because HTML Purifier
            // only checks for classes, that have already been loaded
            // beforehand.
            CacheAdapter::triggerAutoload();
            $purifierCache->register('Kirby', CacheAdapter::class);

            $config = HTMLPurifier_Config::createDefault();
            $config->set('Cache.DefinitionImpl', 'Kirby');

            // Setting a doctype is required to get HTML5-style self-closing
            // tags (<img>) instead of XHTML syntax (<img />)
            $config->set('HTML.Doctype','HTML 4.01 Transitional');

            // Set default text direction
            if (!empty($options['dir'])) {
                $config->set('Attr.DefaultTextDir', $options['dir']);
            } else if ($language = kirby()->language()) {
                $config->set('Attr.DefaultTextDir', $language->direction());
            }

            $config->set('Attr.AllowedRel', ['noopener', 'noreferrer', 'nofollow']);
            $config->set('HTML.Allowed', static::getAllowedElements($options));

            if ($options['allowlinks'] === true) {
                // Enable link processing only, if enabled in site config
                if ($options['autolinks'] === true) {
                    // Recognize URLs in text and turn them into links
                    // automatically.
                    $config->set('AutoFormat.Linkify', true);
                }

                // Add rel="nofollow" to external links to signalize,
                // that these have not been endorsed by the author of
                // the page.
                $config->set('HTML.Nofollow', true);
            }

            $config->set('URI.Host', parse_url(url(), PHP_URL_HOST));
            $config->set('URI.DisableExternalResources', true);
            $config->set('URI.DisableResources', true);
            $config->set('URI.AllowedSchemes', [
                'http' => true,
                'https' => true,
                'mailto' => true,
                'xmpp' => true,
                'irc' => true,
                'ircs' => true,
            ]);

            $config->set('Output.Newline', "\n"); // Use unix line breaks only 🤘

            // Remove empty paragraphs
            $config->set('AutoFormat.RemoveEmpty.RemoveNbsp', true);
            $config->set('AutoFormat.RemoveEmpty', true);

            // Add HTMl5-only elements to the HTML definition, otherwise
            // the purifier would refuse to accept them.
            $def = $config->getHTMLDefinition(true);
            $def->addElement('mark', 'Inline', 'Inline', 'Common');

            // The sanitized code should not contain any classes in the end,
            // with the exception of codeblocks, as generated by a markdown
            // formatting tool, such as Parsedown.

            // The class attribute is allowed on the pre element, but
            // its value can only be `code`.
            $def->addAttribute('pre', 'class', 'Enum#code');

            // The code element only accepts a class name in the format
            // `language-*`, that is used by JavaScript-based syntax
            // highlighters for determing a code block’s language.
            $def->addAttribute('code', 'class', new CodeClassAttrDef());

            // Add rel="noreferrer noopener" to all external links, while
            // "nofollow" has been added by the purifier itself already.
            // "norefferer" and "noopener" are for safety, if another filter or
            // JavaScript code adds target="_blank" to external links.
            $def->info_tag_transform['a'] = new LinkTransformer();

            // Remove links without `href` attribute.
            $def->info_injector[] = new RemoveEmptyLinksInjector();

            static::$purifier = new HTMLPurifier($config);
        }

        // Apply purifier filter
        $text = static::$purifier->purify($text);

        // Move `<br>` tags at the beginning or end of an inline element
        // before/after that element to prevent styling issues (e.g. displaying
        // an icon after a external link).
        $text = preg_replace('#(<(' . implode('|', static::INLINE_ELEMENTS) .')(?:\s+[^>]*)*>)(\s*<br>\s*)*(.*?)(\s*<br>\s*)*(<\/\2>)#siu', '$3$1$4$6$5', $text);

        // Trim `<br>` elements at the beginning or end of block-level elements
        $blocks = implode('|', static::BLOCK_ELEMENTS);
        $text = preg_replace("#(<(?:{$blocks})(?:\s+[^>]*)*>)(\s*<br>\s*)*#siu", '$1', $text);
        $text = preg_replace("#(\s*<br>\s*)*(</(?:{$blocks})(?:\s+[^>]*)*>)#siu", '$2', $text);

        // Remove headlines and replace with paragraphs of bold text, to prevent them
        // from messing with the outline of the containing documnent.
        $text = preg_replace_callback('#(<(h([1-6]))(?:\s+[^>]*)*>)(.*?)(<\/\2>)#siu', function($matches) use ($options) {
            list(,,$tag, $level, $content) = $matches;
            $class = Str::template($options['headingClass'], [
                'tag' => $tag,
                'level' => $level,
            ]);
            return '<p class="' . $class . '"><strong>' . $content . '</strong></p>';
        }, $text);

        // Remove 'code' class from <pre> elements, that do not contain
        // a <code> element as first child, because they should not be
        // considered a code block.
        $text = preg_replace_callback('#(<pre class="code"[^>]*>)(.*?)(</pre>)#siu', function($matches) {
            list($outerHtml, $start, $content, $end) = $matches;
            if (preg_match('#^\s*<code[^>]+#siU', $content)) {
                return $outerHtml;
            }

            return "<pre>{$content}</pre>";
        }, $text);

        return $text;
    }

    /**
     * Converts Markdown formatting on given string into HTML using the
     * Parsedown library.
     *
     * @param array $options Configuration
     * @return string The resulting HTML of the conversion
     */
    protected static function markdown(string $text, array $options): string
    {
        if (static::$parsedown === null) {
            // Using the Parsedown library directly instead of Kirby’s
            // Markdown component to have full control over the settings.
            static::$parsedown = new Parsedown();
            static::$parsedown->setBreaksEnabled(true);
            static::$parsedown->setUrlsLinked($options['allowlinks'] && $options['autolinks']);
        }

        return static::$parsedown->text($text);
    }
}
