<?php
/**
 * FARS
 *
 * Copyright (c) 2025
 *
 * Author: 40x.Pro@gmail.com | github.com/levskiy0
 * Date: 17.09.2025
 */

if (!defined('_PS_VERSION_')) {
    exit;
}

class Ps_Fars extends Module
{
    private const CONFIG_SERVICE_URL = 'FARS_IMAGE_SERVICE';
    private const DEFAULT_SERVICE_URL = 'http://127.0.0.1:9090';
    private const CONFIG_INJECT_JS = 'FARS_INJECT_JS';
    private const CONFIG_ALLOWED_DOMAINS = 'FARS_ALLOWED_DOMAINS';
    private const CONFIG_FORMAT_AVIF = 'FARS_FORMAT_AVIF';
    private const CONFIG_FORMAT_WEBP = 'FARS_FORMAT_WEBP';
    private const CONFIG_FORMAT_PNG = 'FARS_FORMAT_PNG';

    /** @var array<string, bool> */
    private static $registeredSmartyInstances = [];

    /** @var bool */
    private static $frontDefinitionsInjected = false;

    /** @var bool */
    private static $frontInlineInjected = false;

    /** @var array<int, array<string, string>>|null */
    private $pictureFormatsCache = null;

    public function __construct()
    {
        $this->name = 'ps_fars';
        $this->tab = 'administration';
        $this->version = '1.0.0';
        $this->author = 'github.com/levskiy0';
        $this->need_instance = 0;
        $this->bootstrap = true;

        parent::__construct();

        $this->displayName = $this->trans('FARS image service', [], 'Modules.Ps_fars.Admin');
        $this->description = $this->trans('Provides Smarty helpers for the FARS remote image service.', [], 'Modules.Ps_fars.Admin');
        $this->ps_versions_compliancy = ['min' => '8.0', 'max' => _PS_VERSION_];
    }

    public function install()
    {
        return parent::install()
            && Configuration::updateValue(self::CONFIG_SERVICE_URL, self::DEFAULT_SERVICE_URL)
            && Configuration::updateValue(self::CONFIG_INJECT_JS, 1)
            && Configuration::updateValue(self::CONFIG_ALLOWED_DOMAINS, '')
            && Configuration::updateValue(self::CONFIG_FORMAT_AVIF, 1)
            && Configuration::updateValue(self::CONFIG_FORMAT_WEBP, 1)
            && Configuration::updateValue(self::CONFIG_FORMAT_PNG, 0)
            && $this->registerHook('actionDispatcher')
            && $this->registerHook('displayHeader')
            && $this->registerHook('displayFooter')
            && $this->registerHook('displayFooterBefore')
            && $this->registerHook('displayFooterAfter')
            && $this->registerHook('displayBeforeBodyClosingTag')
            && $this->registerHook('displayBackOfficeHeader');
    }

    public function uninstall()
    {
        return Configuration::deleteByName(self::CONFIG_SERVICE_URL)
            && Configuration::deleteByName(self::CONFIG_INJECT_JS)
            && Configuration::deleteByName(self::CONFIG_ALLOWED_DOMAINS)
            && Configuration::deleteByName(self::CONFIG_FORMAT_AVIF)
            && Configuration::deleteByName(self::CONFIG_FORMAT_WEBP)
            && Configuration::deleteByName(self::CONFIG_FORMAT_PNG)
            && parent::uninstall();
    }

    public function hookActionDispatcher($params)
    {
        $this->registerSmartyPlugins();
    }

    public function hookDisplayHeader($params)
    {
        $this->registerSmartyPlugins();
        $this->injectFrontDefinitions();
    }

    public function hookDisplayFooter($params)
    {
        return $this->renderFrontInlineScript();
    }

    public function hookDisplayFooterBefore($params)
    {
        return $this->renderFrontInlineScript();
    }

    public function hookDisplayFooterAfter($params)
    {
        return $this->renderFrontInlineScript();
    }

    public function hookDisplayBeforeBodyClosingTag($params)
    {
        return $this->renderFrontInlineScript();
    }

    public function hookDisplayBackOfficeHeader($params)
    {
        $this->registerSmartyPlugins();
    }

    public function getContent()
    {
        $output = '';
        if (Tools::isSubmit('submitLwFars')) {
            $serviceUrl = trim((string) Tools::getValue(self::CONFIG_SERVICE_URL));
            $injectJsFlag = Tools::getValue(self::CONFIG_INJECT_JS);
            $allowedDomainsRaw = (string) Tools::getValue(
                self::CONFIG_ALLOWED_DOMAINS,
                (string) Configuration::get(self::CONFIG_ALLOWED_DOMAINS)
            );
            $formatAvifFlag = Tools::getValue(
                self::CONFIG_FORMAT_AVIF,
                (string) Configuration::get(self::CONFIG_FORMAT_AVIF)
            );
            $formatWebpFlag = Tools::getValue(
                self::CONFIG_FORMAT_WEBP,
                (string) Configuration::get(self::CONFIG_FORMAT_WEBP)
            );
            $formatPngFlag = Tools::getValue(
                self::CONFIG_FORMAT_PNG,
                (string) Configuration::get(self::CONFIG_FORMAT_PNG)
            );
            $formatAvifNormalized = $this->normalizeBoolean($formatAvifFlag);
            $formatWebpNormalized = $this->normalizeBoolean($formatWebpFlag);
            $formatPngNormalized = $this->normalizeBoolean($formatPngFlag);
            $invalidDomains = [];
            $normalizedAllowedDomains = $this->prepareAllowedDomainsForStorage($allowedDomainsRaw, $invalidDomains);

            if (!Validate::isUrl($serviceUrl) && !Validate::isAbsoluteUrl($serviceUrl)) {
                $output .= $this->displayError($this->trans('Please provide a valid URL.', [], 'Modules.Ps_fars.Admin'));
            } else {
                Configuration::updateValue(self::CONFIG_SERVICE_URL, $serviceUrl);
                Configuration::updateValue(self::CONFIG_INJECT_JS, $this->normalizeBoolean($injectJsFlag) ? 1 : 0);
                Configuration::updateValue(self::CONFIG_ALLOWED_DOMAINS, $normalizedAllowedDomains);
                Configuration::updateValue(
                    self::CONFIG_FORMAT_AVIF,
                    $formatAvifNormalized !== false ? 1 : 0
                );
                Configuration::updateValue(
                    self::CONFIG_FORMAT_WEBP,
                    $formatWebpNormalized !== false ? 1 : 0
                );
                Configuration::updateValue(
                    self::CONFIG_FORMAT_PNG,
                    $formatPngNormalized ? 1 : 0
                );
                $this->pictureFormatsCache = null;
                $output .= $this->displayConfirmation($this->trans('Settings updated.', [], 'Modules.Ps_fars.Admin'));

                if (!empty($invalidDomains)) {
                    $output .= $this->displayWarning($this->trans(
                        'The following domains were skipped because they are invalid: %domains%',
                        ['%domains%' => implode(', ', $invalidDomains)],
                        'Modules.Ps_fars.Admin'
                    ));
                }
            }
        }

        return $output . $this->renderForm();
    }

    private function renderForm()
    {
        $helper = new HelperForm();
        $helper->module = $this;
        $helper->table = $this->table;
        $helper->default_form_language = $this->context->language->id;
        $helper->allow_employee_form_lang = (int) Configuration::get('PS_BO_ALLOW_EMPLOYEE_FORM_LANG', 0);
        $helper->identifier = $this->identifier;
        $helper->submit_action = 'submitLwFars';
        $helper->currentIndex = $this->context->link->getAdminLink('AdminModules', false)
            . '&configure=' . $this->name
            . '&tab_module=' . $this->tab
            . '&module_name=' . $this->name;
        $helper->token = Tools::getAdminTokenLite('AdminModules');
        $helper->tpl_vars = [
            'fields_value' => [
                self::CONFIG_SERVICE_URL => Configuration::get(self::CONFIG_SERVICE_URL) ?: self::DEFAULT_SERVICE_URL,
                self::CONFIG_INJECT_JS => (int) Configuration::get(self::CONFIG_INJECT_JS) === 1,
                self::CONFIG_ALLOWED_DOMAINS => Tools::getValue(
                    self::CONFIG_ALLOWED_DOMAINS,
                    (string) Configuration::get(self::CONFIG_ALLOWED_DOMAINS)
                ),
                self::CONFIG_FORMAT_AVIF => (int) Configuration::get(self::CONFIG_FORMAT_AVIF) !== 0,
                self::CONFIG_FORMAT_WEBP => (int) Configuration::get(self::CONFIG_FORMAT_WEBP) !== 0,
                self::CONFIG_FORMAT_PNG => (int) Configuration::get(self::CONFIG_FORMAT_PNG) !== 0,
            ],
        ];

        $baseHosts = $this->getBaseAllowedHosts();
        $baseHostsText = $baseHosts
            ? implode(', ', $baseHosts)
            : $this->trans('No shop domains detected', [], 'Modules.Ps_fars.Admin');

        $fieldsForm = [
            'form' => [
                'legend' => [
                    'title' => $this->trans('Settings', [], 'Modules.Ps_fars.Admin'),
                    'icon' => 'icon-cogs',
                ],
                'input' => [
                    [
                        'type' => 'text',
                        'label' => $this->trans('Service URL', [], 'Modules.Ps_fars.Admin'),
                        'name' => self::CONFIG_SERVICE_URL,
                        'required' => true,
                        'hint' => $this->trans('Base URL to your FARS image service (e.g. https://fars.example.com).', [], 'Modules.Ps_fars.Admin'),
                    ],
                    [
                        'type' => 'textarea',
                        'label' => $this->trans('Additional allowed domains', [], 'Modules.Ps_fars.Admin'),
                        'name' => self::CONFIG_ALLOWED_DOMAINS,
                        'rows' => 4,
                        'cols' => 40,
                        'placeholder' => "cdn.example.com\nmedia.example.org",
                        'hint' => $this->trans('Add extra domains (one per line, host only) that should be treated as local images for smart content.', [], 'Modules.Ps_fars.Admin'),
                        'desc' => $this->trans(
                            'Current shop domains allowed automatically: %domains%. These domains are always rewritten.',
                            ['%domains%' => $baseHostsText],
                            'Modules.Ps_fars.Admin'
                        ),
                    ],
                    [
                        'type' => 'switch',
                        'label' => $this->trans('Generate AVIF sources', [], 'Modules.Ps_fars.Admin'),
                        'name' => self::CONFIG_FORMAT_AVIF,
                        'is_bool' => true,
                        'values' => [
                            [
                                'id' => 'format_avif_on',
                                'value' => 1,
                                'label' => $this->trans('On', [], 'Modules.Ps_fars.Admin'),
                            ],
                            [
                                'id' => 'format_avif_off',
                                'value' => 0,
                                'label' => $this->trans('Off', [], 'Modules.Ps_fars.Admin'),
                            ],
                        ],
                        'hint' => $this->trans('Toggle AVIF `<source>` generation inside rendered `<picture>` blocks.', [], 'Modules.Ps_fars.Admin'),
                    ],
                    [
                        'type' => 'switch',
                        'label' => $this->trans('Generate WebP sources', [], 'Modules.Ps_fars.Admin'),
                        'name' => self::CONFIG_FORMAT_WEBP,
                        'is_bool' => true,
                        'values' => [
                            [
                                'id' => 'format_webp_on',
                                'value' => 1,
                                'label' => $this->trans('On', [], 'Modules.Ps_fars.Admin'),
                            ],
                            [
                                'id' => 'format_webp_off',
                                'value' => 0,
                                'label' => $this->trans('Off', [], 'Modules.Ps_fars.Admin'),
                            ],
                        ],
                        'hint' => $this->trans('Toggle WebP `<source>` generation inside rendered `<picture>` blocks.', [], 'Modules.Ps_fars.Admin'),
                    ],
                    [
                        'type' => 'switch',
                        'label' => $this->trans('Generate PNG sources', [], 'Modules.Ps_fars.Admin'),
                        'name' => self::CONFIG_FORMAT_PNG,
                        'is_bool' => true,
                        'values' => [
                            [
                                'id' => 'format_png_on',
                                'value' => 1,
                                'label' => $this->trans('On', [], 'Modules.Ps_fars.Admin'),
                            ],
                            [
                                'id' => 'format_png_off',
                                'value' => 0,
                                'label' => $this->trans('Off', [], 'Modules.Ps_fars.Admin'),
                            ],
                        ],
                        'hint' => $this->trans('Toggle PNG `<source>` generation inside rendered `<picture>` blocks.', [], 'Modules.Ps_fars.Admin'),
                    ],
                    [
                        'type' => 'switch',
                        'label' => $this->trans('Inject JS helper', [], 'Modules.Ps_fars.Admin'),
                        'name' => self::CONFIG_INJECT_JS,
                        'is_bool' => true,
                        'values' => [
                            [
                                'id' => 'inject_js_on',
                                'value' => 1,
                                'label' => $this->trans('On', [], 'Modules.Ps_fars.Admin'),
                            ],
                            [
                                'id' => 'inject_js_off',
                                'value' => 0,
                                'label' => $this->trans('Off', [], 'Modules.Ps_fars.Admin'),
                            ],
                        ],
                        'hint' => $this->trans('Toggle injection of the frontend JS helper (window.fars_url). Disable if you embed a custom bundle instead.', [], 'Modules.Ps_fars.Admin'),
                    ],
                ],
                'submit' => [
                    'title' => $this->trans('Save', [], 'Modules.Ps_fars.Admin'),
                ],
            ],
        ];

        return $helper->generateForm([$fieldsForm]);
    }

    private function registerSmartyPlugins(): void
    {
        $smarty = $this->context->smarty;
        if (!$smarty) {
            return;
        }

        $instanceId = spl_object_hash($smarty);
        if (isset(self::$registeredSmartyInstances[$instanceId])) {
            return;
        }

        $smarty->registerPlugin('function', 'fars_url', [$this, 'smartyFarsUrl'], true);
        $smarty->registerPlugin('function', 'fars_product_url', [$this, 'smartyFarsProductUrl'], true);
        $smarty->registerPlugin('function', 'fars_picture', [$this, 'smartyFarsPicture'], true);
        $smarty->registerPlugin('function', 'fars_image', [$this, 'smartyFarsImage'], true);
        $smarty->registerPlugin('function', 'fars_smart_content', [$this, 'smartyFarsSmartContent'], true);
        $smarty->registerPlugin('modifier', 'fars_smart_content', [$this, 'smartyFarsSmartContentModifier'], true);

        self::$registeredSmartyInstances[$instanceId] = true;
    }

    private function injectFrontDefinitions(): void
    {
        if (self::$frontDefinitionsInjected) {
            return;
        }

        if (!$this->isFrontControllerContext()) {
            return;
        }

        if (!$this->shouldInjectJs()) {
            return;
        }

        Media::addJsDef([
            'farsResizeServiceUrl' => $this->getServiceUrl(),
        ]);

        self::$frontDefinitionsInjected = true;
    }

    private function renderFrontInlineScript(): string
    {
        if (self::$frontInlineInjected) {
            return '';
        }

        if (!$this->isFrontControllerContext()) {
            return '';
        }

        if (!$this->shouldInjectJs()) {
            return '';
        }

        $this->injectFrontDefinitions();

        $smarty = $this->context->smarty;
        if (!$smarty) {
            return '';
        }

        $smarty->assign([
            'farsServiceUrl' => $this->getServiceUrl(),
        ]);

        self::$frontInlineInjected = true;

        return $this->display(__FILE__, 'views/templates/hook/fars_inline_js.tpl');
    }

    private function isFrontControllerContext(): bool
    {
        if (!$this->context || !$this->context->controller) {
            return false;
        }

        $type = $this->context->controller->controller_type ?? '';

        return in_array($type, ['front', 'modulefront'], true);
    }

    public function smartyFarsUrl($params, Smarty_Internal_Template $template)
    {
        $width = isset($params['width']) ? (int) $params['width'] : (isset($params['w']) ? (int) $params['w'] : 0);
        $height = isset($params['height']) ? (int) $params['height'] : (isset($params['h']) ? (int) $params['h'] : 0);
        $source = $params['src'] ?? ($params['url'] ?? '');
        $format = $params['format'] ?? '';

        if (!$source) {
            return '';
        }

        $baseUrl = $this->buildResizeBase($this->cleanSourcePath($source), $width, $height);
        if (!$baseUrl) {
            return '';
        }

        if ($format) {
            $format = ltrim((string) $format, '.');
            $baseUrl .= '.' . $format;
        }

        return $baseUrl;
    }

    public function smartyFarsProductUrl($params, Smarty_Internal_Template $template)
    {
        $product = $params['product'] ?? null;
        $format = $params['format'] ?? '';
        $width = isset($params['width']) ? (int) $params['width'] : (isset($params['w']) ? (int) $params['w'] : 0);
        $height = isset($params['height']) ? (int) $params['height'] : (isset($params['h']) ? (int) $params['h'] : 0);
        $idImage = isset($params['id_image']) ? (int) $params['id_image'] : null;

        $path = $this->resolveProductImagePath($product, $idImage);
        if (!$path) {
            return '';
        }

        $url = $this->buildResizeBase($path, $width, $height);
        if (!$url) {
            return '';
        }

        if ($format) {
            $format = ltrim((string) $format, '.');
            $url .= '.' . $format;
        }

        return $url;
    }

    private function renderPictureMarkup(array $params, Smarty_Internal_Template $template, string $mode): string
    {
        $params['mode'] = $mode;
        $prepared = array_merge([], $this->prepareRenderParams($params));

        return $this->renderTemplate($template, 'views/templates/picture.tpl', $prepared);
    }

    public function smartyFarsPicture($params, Smarty_Internal_Template $template)
    {
        return $this->renderPictureMarkup($params, $template, 'picture');
    }

    public function smartyFarsImage($params, Smarty_Internal_Template $template)
    {
        return $this->renderPictureMarkup($params, $template, 'img');
    }

    public function smartyFarsSmartContent($params, Smarty_Internal_Template $template)
    {
        $html = '';

        if (isset($params['html'])) {
            $html = (string) $params['html'];
        } elseif (isset($params['content'])) {
            $html = (string) $params['content'];
        } elseif (!empty($params)) {
            $firstParam = reset($params);
            if (is_string($firstParam)) {
                $html = $firstParam;
            }
        }

        $maxWidth = null;
        foreach (['max_width', 'maxWidth', 'width_limit'] as $key) {
            if (isset($params[$key])) {
                $maxWidth = $this->parseDimension($params[$key]);
                break;
            }
        }

        $lazyFlag = null;
        foreach (['loading_lazy', 'lazy', 'lazy_loading'] as $key) {
            if (array_key_exists($key, $params)) {
                $lazyFlag = $this->normalizeBoolean($params[$key]);
                break;
            }
        }

        $fallbacksSpec = null;
        foreach (['fallbacks', 'breakpoints', 'sources'] as $key) {
            if (isset($params[$key])) {
                $fallbacksSpec = $params[$key];
                break;
            }
        }

        $fallbacks = $this->extractSmartContentFallbacks($fallbacksSpec);

        return $this->transformSmartContent($html, $maxWidth, $lazyFlag, $fallbacks);
    }

    public function smartyFarsSmartContentModifier($html, $maxWidth = null, $lazyFlag = null, $fallbacks = null)
    {
        $maxWidthValue = $this->parseDimension($maxWidth);
        $lazyValue = $this->normalizeBoolean($lazyFlag);
        $fallbacksValue = $this->extractSmartContentFallbacks($fallbacks);

        return $this->transformSmartContent((string) $html, $maxWidthValue, $lazyValue, $fallbacksValue);
    }

    private function transformSmartContent(string $html, ?int $maxWidth = null, ?bool $forceLazy = null, array $fallbacks = []): string
    {
        if ($html === '') {
            return '';
        }

        $encodedHtml = '<div>' . $html . '</div>';
        $dom = new DOMDocument('1.0', 'UTF-8');
        $previous = libxml_use_internal_errors(true);
        $loaded = $dom->loadHTML(
            '<?xml encoding="utf-8" ?>' . $encodedHtml,
            LIBXML_HTML_NOIMPLIED | LIBXML_HTML_NODEFDTD | LIBXML_PARSEHUGE
        );
        libxml_clear_errors();
        libxml_use_internal_errors($previous);

        if (!$loaded) {
            return $html;
        }

        $images = $dom->getElementsByTagName('img');
        if (!$images->length) {
            return $html;
        }

        $maxWidth = $maxWidth !== null && $maxWidth > 0 ? $maxWidth : null;

        /** @var DOMElement $image */
        foreach ($images as $image) {
            $src = (string) $image->getAttribute('src');

            if (!$this->isLocalImageSrc($src)) {
                continue;
            }

            $parent = $image->parentNode;
            if ($parent && strtolower($parent->nodeName) === 'picture') {
                continue;
            }

            $width = $this->parseDimension($image->getAttribute('width'));
            $height = $this->parseDimension($image->getAttribute('height'));
            $originalWidthAttr = $image->hasAttribute('width') ? $image->getAttribute('width') : '';
            $originalHeightAttr = $image->hasAttribute('height') ? $image->getAttribute('height') : '';
            $originalWidth = $width;
            $originalHeight = $height;
            $widthAdjusted = false;
            $heightAdjusted = false;

            if ($maxWidth !== null) {
                if ($width === 0 && $height === 0) {
                    $width = $maxWidth;
                    $widthAdjusted = true;
                } elseif ($width > 0 && $width > $maxWidth) {
                    if ($height > 0) {
                        $height = (int) round($height * ($maxWidth / $width));
                    }
                    $width = $maxWidth;
                    $widthAdjusted = true;
                    $heightAdjusted = $height !== $originalHeight;
                }
            }

            if ($width === 0 && $height === 0) {
                continue;
            }

            $cleanPath = $this->cleanSourcePath($src);
            if ($cleanPath === '') {
                continue;
            }

            $base1x = $this->buildResizeBase($cleanPath, $width, $height);
            if ($base1x === '') {
                continue;
            }

            $doubleWidth = $width > 0 ? $width * 2 : 0;
            $doubleHeight = $height > 0 ? $height * 2 : 0;
            $base2x = $this->buildResizeBase($cleanPath, $doubleWidth, $doubleHeight);
            if ($base2x === '') {
                continue;
            }

            $resolvedFallbacks = $this->resolveSmartContentFallbackSources(
                $fallbacks,
                $cleanPath,
                $maxWidth,
                $width
            );

            $pictureMarkup = $this->buildSmartContentPictureMarkup(
                $image,
                $base1x,
                $base2x,
                $width,
                $height,
                $originalWidthAttr,
                $originalHeightAttr,
                $widthAdjusted,
                $heightAdjusted,
                $forceLazy,
                $resolvedFallbacks
            );

            if ($pictureMarkup === '') {
                continue;
            }

            $fragment = $dom->createDocumentFragment();
            if (@$fragment->appendXML($pictureMarkup)) {
                $image->parentNode->replaceChild($fragment, $image);
            }
        }

        $wrapper = $dom->getElementsByTagName('div')->item(0);
        if (!$wrapper) {
            return $html;
        }

        $result = '';
        foreach ($wrapper->childNodes as $child) {
            $result .= $dom->saveHTML($child);
        }

        return $result;
    }

    private function buildSmartContentPictureMarkup(
        DOMElement $image,
        string $base1x,
        string $base2x,
        int $width,
        int $height,
        string $originalWidthAttr,
        string $originalHeightAttr,
        bool $widthAdjusted,
        bool $heightAdjusted,
        ?bool $forceLazy,
        array $fallbackSources
    ): string {
        $attributes = [];
        foreach ($image->attributes as $attr) {
            $attributes[strtolower($attr->name)] = (string) $attr->value;
        }

        $class = $attributes['class'] ?? '';
        $style = $attributes['style'] ?? '';
        $alt = array_key_exists('alt', $attributes) ? $attributes['alt'] : '';
        $title = $attributes['title'] ?? '';
        $decoding = $attributes['decoding'] ?? '';
        $fetchpriority = $attributes['fetchpriority'] ?? '';
        $sizes = $attributes['sizes'] ?? '';

        $loading = $attributes['loading'] ?? '';
        if ($forceLazy === true) {
            $loading = 'lazy';
        } elseif ($forceLazy === false) {
            $loading = '';
        } elseif ($loading === '') {
            $loading = 'lazy';
        }

        if ($decoding === '') {
            $decoding = 'async';
        }

        if ($fetchpriority === '') {
            $fetchpriority = 'low';
        }

        $widthAttrOut = '';
        if ($widthAdjusted || $originalWidthAttr === '') {
            if ($width > 0) {
                $widthAttrOut = (string) $width;
            }
        } else {
            $widthAttrOut = $originalWidthAttr;
        }

        $heightAttrOut = '';
        if ($heightAdjusted || $originalHeightAttr === '') {
            if ($height > 0) {
                $heightAttrOut = (string) $height;
            }
        } else {
            $heightAttrOut = $originalHeightAttr;
        }

        $dataAttributes = [];
        $ariaAttributes = [];
        $miscAttributes = [];

        $reserved = [
            'src',
            'srcset',
            'width',
            'height',
            'loading',
            'class',
            'style',
            'alt',
            'title',
            'decoding',
            'fetchpriority',
            'sizes',
        ];

        foreach ($attributes as $name => $value) {
            if (strpos($name, 'data-') === 0) {
                $dataAttributes[$name] = $value;
                continue;
            }

            if (strpos($name, 'aria-') === 0 || $name === 'role') {
                $ariaAttributes[$name] = $value;
                continue;
            }

            if (in_array($name, $reserved, true)) {
                continue;
            }

            $miscAttributes[$name] = $value;
        }

        $sources = [];
        $formats = $this->getPictureFormats();
        foreach ($fallbackSources as $fallbackSource) {
            $media = $fallbackSource['media'] ?? '';
            $mediaAttr = $media !== '' ? ' media="' . $this->escapeAttribute($media) . '"' : '';

            foreach ($formats as $format) {
                $sources[] = sprintf(
                    '<source%s type="%s" srcset="%s, %s 2x"/>',
                    $mediaAttr,
                    $this->escapeAttribute($format['mime']),
                    $this->escapeAttribute($fallbackSource['base1x'] . $format['extension']),
                    $this->escapeAttribute($fallbackSource['base2x'] . $format['extension'])
                );
            }
        }
        foreach ($formats as $format) {
            $sources[] = sprintf(
                '<source type="%s" srcset="%s, %s 2x"/>',
                $this->escapeAttribute($format['mime']),
                $this->escapeAttribute($base1x . $format['extension']),
                $this->escapeAttribute($base2x . $format['extension'])
            );
        }

        $imgAttributes = [];
        if ($class !== '') {
            $imgAttributes[] = 'class="' . $this->escapeAttribute($class) . '"';
        }
        if ($style !== '') {
            $imgAttributes[] = 'style="' . $this->escapeAttribute($style) . '"';
        }
        if ($title !== '') {
            $imgAttributes[] = 'title="' . $this->escapeAttribute($title) . '"';
        }

        $imgAttributes[] = 'src="' . $this->escapeAttribute($base1x) . '"';

        if ($alt !== '') {
            $imgAttributes[] = 'alt="' . $this->escapeAttribute($alt) . '"';
        } else {
            $imgAttributes[] = 'alt=""';
        }

        if ($loading !== '') {
            $imgAttributes[] = 'loading="' . $this->escapeAttribute($loading) . '"';
        }

        if ($decoding !== '') {
            $imgAttributes[] = 'decoding="' . $this->escapeAttribute($decoding) . '"';
        }

        if ($fetchpriority !== '') {
            $imgAttributes[] = 'fetchpriority="' . $this->escapeAttribute($fetchpriority) . '"';
        }

        $imgAttributes[] = 'srcset="' . $this->escapeAttribute($base1x . ' 1x, ' . $base2x . ' 2x') . '"';

        if ($sizes !== '') {
            $imgAttributes[] = 'sizes="' . $this->escapeAttribute($sizes) . '"';
        }

        if ($widthAttrOut !== '') {
            $imgAttributes[] = 'width="' . $this->escapeAttribute($widthAttrOut) . '"';
        }

        if ($heightAttrOut !== '') {
            $imgAttributes[] = 'height="' . $this->escapeAttribute($heightAttrOut) . '"';
        }

        foreach ($dataAttributes as $name => $value) {
            $imgAttributes[] = $name . '="' . $this->escapeAttribute($value) . '"';
        }

        foreach ($ariaAttributes as $name => $value) {
            $imgAttributes[] = $name . '="' . $this->escapeAttribute($value) . '"';
        }

        foreach ($miscAttributes as $name => $value) {
            $imgAttributes[] = $name . '="' . $this->escapeAttribute($value) . '"';
        }

        $picture = '<picture>'
            . implode('', $sources)
            . '<img ' . implode(' ', $imgAttributes) . ' />'
            . '</picture>';

        return $picture;
    }

    private function extractSmartContentFallbacks($fallbacks): array
    {
        if ($fallbacks === null) {
            return [];
        }

        if (is_string($fallbacks)) {
            $trimmed = trim($fallbacks);
            if ($trimmed === '') {
                return [];
            }

            $decoded = json_decode($trimmed, true);
            if (json_last_error() === JSON_ERROR_NONE) {
                $fallbacks = $decoded;
            } else {
                return [];
            }
        }

        return $this->normalizeFallbacks($fallbacks);
    }

    private function resolveSmartContentFallbackSources(
        array $fallbacks,
        string $cleanPath,
        ?int $maxWidth,
        int $baseWidth
    ): array
    {
        if (empty($fallbacks)) {
            return [];
        }

        $resolved = [];

        foreach ($fallbacks as $fallback) {
            $fw = (int) ($fallback['w'] ?? 0);
            $fh = (int) ($fallback['h'] ?? 0);
            $media = isset($fallback['media']) ? (string) $fallback['media'] : '';

            $widthLimit = null;
            if ($maxWidth !== null && $maxWidth > 0) {
                $widthLimit = $maxWidth;
            }

            if ($baseWidth > 0) {
                $widthLimit = $widthLimit === null ? $baseWidth : min($widthLimit, $baseWidth);
            }

            if ($widthLimit !== null) {
                if ($fw === 0 && $fh === 0) {
                    $fw = $widthLimit;
                } elseif ($fw > $widthLimit) {
                    continue;
                }
            }

            if ($fw === 0 && $fh === 0) {
                continue;
            }

            $base1x = $this->buildResizeBase($cleanPath, $fw, $fh);
            if ($base1x === '') {
                continue;
            }

            $doubleWidth = $fw > 0 ? $fw * 2 : 0;
            $doubleHeight = $fh > 0 ? $fh * 2 : 0;
            $base2x = $this->buildResizeBase($cleanPath, $doubleWidth, $doubleHeight);
            if ($base2x === '') {
                $base2x = $base1x;
            }

            $resolved[] = [
                'media' => $media,
                'base1x' => $base1x,
                'base2x' => $base2x,
            ];
        }

        return $resolved;
    }

    private function normalizeBoolean($value): ?bool
    {
        if ($value === null) {
            return null;
        }

        if (is_bool($value)) {
            return $value;
        }

        if (is_int($value) || is_float($value)) {
            return (bool) $value;
        }

        if (is_string($value)) {
            $normalized = strtolower(trim($value));
            if ($normalized === '') {
                return null;
            }

            if (in_array($normalized, ['1', 'true', 'yes', 'on'], true)) {
                return true;
            }

            if (in_array($normalized, ['0', 'false', 'no', 'off'], true)) {
                return false;
            }
        }

        return null;
    }

    private function renderTemplate(Smarty_Internal_Template $template, string $templatePath, array $variables): string
    {
        $cacheId = md5($templatePath . ':' . implode('||', $variables));

        $tpl = $template->smarty->createTemplate(
            'module:' . $this->name . '/' . ltrim($templatePath, '/'),
            $template->cache_id . ':' . $cacheId,
            $template->compile_id,
            $template,
            true
        );

        $tpl->assign($variables);

        return $tpl->fetch();
    }

    private function prepareRenderParams(array $params): array
    {
        $width = $this->parseDimension($params['w'] ?? ($params['width'] ?? null));
        $height = $this->parseDimension($params['h'] ?? ($params['height'] ?? null));

        $fallbacks = $this->normalizeFallbacks($params['fallbacks'] ?? []);

        return [
            'service' => $this->getServiceUrl(),
            'src' => $this->cleanSourcePath((string) ($params['url'] ?? ($params['src'] ?? ($params['image'] ?? '')))),
            'w' => $width,
            'h' => $height,
            'mode' => (string) ($params['mode'] ?? 'picture'),
            'class' => (string) ($params['class'] ?? ''),
            'style' => (string) ($params['style'] ?? ''),
            'alt' => (string) ($params['alt'] ?? ''),
            'loading' => (string) ($params['loading'] ?? 'lazy'),
            'fetchpriority' => (string) ($params['fetchpriority'] ?? 'low'),
            'decoding' => (string) ($params['decoding'] ?? 'async'),
            'data' => (array) ($params['data'] ?? []),
            'fallbacks' => $fallbacks,
            'sizes' => (string) ($params['sizes'] ?? ''),
            'formats' => $this->getPictureFormats(),
        ];
    }

    private function getPictureFormats(): array
    {
        if ($this->pictureFormatsCache !== null) {
            return $this->pictureFormatsCache;
        }

        $formats = [];

        if ((int) Configuration::get(self::CONFIG_FORMAT_AVIF) !== 0) {
            $formats[] = [
                'key' => 'avif',
                'extension' => '.avif',
                'mime' => 'image/avif',
            ];
        }

        if ((int) Configuration::get(self::CONFIG_FORMAT_WEBP) !== 0) {
            $formats[] = [
                'key' => 'webp',
                'extension' => '.webp',
                'mime' => 'image/webp',
            ];
        }

        if ((int) Configuration::get(self::CONFIG_FORMAT_PNG) !== 0) {
            $formats[] = [
                'key' => 'png',
                'extension' => '.png',
                'mime' => 'image/png',
            ];
        }

        $formats[] = [
            'key' => 'jpeg',
            'extension' => '',
            'mime' => 'image/jpeg',
        ];

        $this->pictureFormatsCache = $formats;

        return $this->pictureFormatsCache;
    }

    private function normalizeFallbacks($fallbacks): array
    {
        if ($fallbacks instanceof Traversable) {
            $fallbacks = iterator_to_array($fallbacks);
        }

        if (!is_array($fallbacks)) {
            return [];
        }

        $normalized = [];

        foreach ($fallbacks as $fallback) {
            if ($fallback instanceof Traversable) {
                $fallback = iterator_to_array($fallback);
            }

            if (!is_array($fallback)) {
                continue;
            }

            $normalized[] = [
                'w' => $this->parseDimension($fallback['w'] ?? ($fallback['width'] ?? null)),
                'h' => $this->parseDimension($fallback['h'] ?? ($fallback['height'] ?? null)),
                'media' => isset($fallback['media']) ? (string) $fallback['media'] : '',
            ];
        }

        return $normalized;
    }

    private function parseDimension($value): int
    {
        if ($value === null) {
            return 0;
        }

        if (is_int($value)) {
            return max(0, $value);
        }

        if (is_float($value)) {
            return max(0, (int) $value);
        }

        if (is_string($value) && preg_match('/(\d+)/', $value, $matches)) {
            return (int) $matches[1];
        }

        return 0;
    }

    private function prepareAllowedDomainsForStorage(string $raw, array &$invalidDomains = []): string
    {
        $invalidDomains = [];
        $validDomains = [];

        foreach ($this->tokenizeDomainList($raw) as $token) {
            $normalized = $this->normalizeDomainToken($token);
            if ($normalized === '') {
                $invalidDomains[] = $token;
                continue;
            }

            $validDomains[] = $normalized;
        }

        $validDomains = array_values(array_unique($validDomains));

        return implode("\n", $validDomains);
    }

    private function tokenizeDomainList(string $raw): array
    {
        if ($raw === '') {
            return [];
        }

        $raw = str_replace(["\r\n", "\r"], "\n", $raw);
        $tokens = preg_split('/[\n,]+/', $raw) ?: [];

        $result = [];
        foreach ($tokens as $token) {
            $token = trim((string) $token);
            if ($token === '') {
                continue;
            }

            $result[] = $token;
        }

        return $result;
    }

    private function normalizeDomainToken(string $token): string
    {
        $token = trim($token);
        if ($token === '') {
            return '';
        }

        $token = preg_replace('#^https?://#i', '', $token);
        if (!$token) {
            return '';
        }

        $token = preg_replace('#/.*$#', '', $token);
        if (!$token) {
            return '';
        }

        if (strpos($token, ':') !== false) {
            $parts = explode(':', $token, 2);
            $token = $parts[0];
        }

        $token = trim($token, '.');
        $token = strtolower($token);

        if ($token === '') {
            return '';
        }

        if (!preg_match('/^(?:[a-z0-9](?:[a-z0-9-]{0,61}[a-z0-9])?)(?:\.(?:[a-z0-9](?:[a-z0-9-]{0,61}[a-z0-9])?))*$/', $token)) {
            return '';
        }

        return $token;
    }

    private function getAdditionalAllowedDomains(): array
    {
        $raw = (string) Configuration::get(self::CONFIG_ALLOWED_DOMAINS);
        if ($raw === '') {
            return [];
        }

        $domains = [];
        foreach ($this->tokenizeDomainList($raw) as $token) {
            $normalized = $this->normalizeDomainToken($token);
            if ($normalized === '') {
                continue;
            }

            $domains[] = $normalized;
        }

        return array_values(array_unique($domains));
    }

    private function getBaseAllowedHosts(): array
    {
        $base = array_filter(array_unique([
            (string) Configuration::get('PS_SHOP_DOMAIN'),
            (string) Configuration::get('PS_SHOP_DOMAIN_SSL'),
            Tools::getShopDomain(),
            Tools::getShopDomainSsl(),
        ]));

        $normalized = [];
        foreach ($base as $host) {
            $normalizedHost = $this->normalizeDomainToken($host);
            if ($normalizedHost === '') {
                continue;
            }

            $normalized[] = $normalizedHost;
        }

        return array_values(array_unique($normalized));
    }

    private function getAllowedHosts(): array
    {
        return array_values(array_unique(array_merge(
            $this->getBaseAllowedHosts(),
            $this->getAdditionalAllowedDomains()
        )));
    }

    private function isLocalImageSrc(string $src): bool
    {
        if ($src === '') {
            return false;
        }

        if (strpos($src, 'data:') === 0) {
            return false;
        }

        $allowedHosts = $this->getAllowedHosts();

        if (preg_match('#^https?://#i', $src)) {
            $host = parse_url($src, PHP_URL_HOST);
            if (!$host) {
                return false;
            }

            $normalizedHost = $this->normalizeDomainToken($host);

            return $normalizedHost !== '' && in_array($normalizedHost, $allowedHosts, true);
        }

        if (strpos($src, '//') === 0) {
            $host = parse_url('https:' . $src, PHP_URL_HOST);
            if (!$host) {
                return false;
            }

            $normalizedHost = $this->normalizeDomainToken($host);

            return $normalizedHost !== '' && in_array($normalizedHost, $allowedHosts, true);
        }

        if (preg_match('#^[a-z]+:#i', $src)) {
            return false;
        }

        return true;
    }

    private function buildResizeBase(string $cleanPath, int $width, int $height): string
    {
        $serviceUrl = $this->getServiceUrl();
        if ($cleanPath === '') {
            return '';
        }

        $normalizedPath = '/' . ltrim($cleanPath, '/');
        $serviceUrl = rtrim($serviceUrl, '/');

        $sizeSpecification = $this->buildSizeSpecification($width, $height);

        return sprintf('%s/resize/%s%s', $serviceUrl, $sizeSpecification, $normalizedPath);
    }

    private function buildSizeSpecification(int $width, int $height): string
    {
        $normalizedWidth = $width > 0 ? (string) $width : '';
        $normalizedHeight = $height > 0 ? (string) $height : '';

        if ($normalizedWidth === '' && $normalizedHeight === '') {
            return '0x0';
        }

        return $normalizedWidth . 'x' . $normalizedHeight;
    }

    private function cleanSourcePath(string $source): string
    {
        if ($source === '') {
            return '';
        }

        $clean = preg_replace('#^(?:https?:)?//[^/]+#', '', $source);
        if (!$clean) {
            $clean = $source;
        }

        if (str_contains($clean, ' ')) {
            $clean = str_replace(' ', '%20', $clean);
        }

        return $clean;
    }

    private function shouldInjectJs(): bool
    {
        static $cached = null;

        if ($cached !== null) {
            return $cached;
        }

        $raw = Configuration::get(self::CONFIG_INJECT_JS);
        if ($raw === false || $raw === null || $raw === '') {
            $cached = true;

            return $cached;
        }

        $cached = (int) $raw !== 0;

        return $cached;
    }

    private function getServiceUrl(): string
    {
        $service = Configuration::get(self::CONFIG_SERVICE_URL);

        return $service ? (string) $service : self::DEFAULT_SERVICE_URL;
    }

    private function escapeAttribute(string $value): string
    {
        return htmlspecialchars($value, ENT_QUOTES, 'UTF-8');
    }

    private function resolveProductImagePath($product, ?int $idImage = null): string
    {
        $resolvedId = $idImage ?? $this->extractProductImageId($product);
        if (!$resolvedId) {
            return '';
        }

        $image = new Image($resolvedId);
        $existingPath = $image->getExistingImgPath();
        if (!$existingPath) {
            return '';
        }

        return '/img/p/' . ltrim($existingPath, '/') . '.' . $image->image_format;
    }

    private function extractProductImageId($product): int
    {
        $data = [];
        if (is_array($product)) {
            $data = $product;
        } elseif ($product instanceof Traversable) {
            $data = iterator_to_array($product);
        } elseif ($product instanceof ArrayAccess) {
            $keysToCopy = ['id_image', 'cover', 'cover_id', 'id_default_image', 'images'];
            foreach ($keysToCopy as $key) {
                if ($product->offsetExists($key)) {
                    $data[$key] = $product[$key];
                }
            }
        }

        $candidates = [
            $data['id_image'] ?? null,
            $data['cover_id'] ?? null,
            $data['id_default_image'] ?? null,
        ];

        if (!empty($data['cover'])) {
            $cover = $data['cover'];
            if (is_array($cover) || $cover instanceof ArrayAccess || is_object($cover)) {
                $coverId = $this->extractImageId($this->getProductDataValue($cover, 'id_image'));
                if ($coverId) {
                    return $coverId;
                }
            }
        }

        if (!empty($data['images'])) {
            $images = $data['images'];
            if ($images instanceof Traversable) {
                $images = iterator_to_array($images);
            }
            if (is_array($images)) {
                foreach ($images as $image) {
                    $imageId = $this->extractImageId($this->getProductDataValue($image, 'id_image'));
                    if ($imageId) {
                        $candidates[] = $imageId;
                        if ($this->isTruthy($this->getProductDataValue($image, 'cover'))) {
                            return $imageId;
                        }
                    }
                }
            }
        }

        foreach ($candidates as $candidate) {
            $id = $this->extractImageId($candidate);
            if ($id) {
                return $id;
            }
        }

        if (is_object($product) && isset($product->id)) {
            $cover = Product::getCover((int) $product->id);
            $id = $this->extractImageId($cover['id_image'] ?? null);
            if ($id) {
                return $id;
            }
        }

        return 0;
    }

    private function extractImageId($value): int
    {
        if (is_int($value)) {
            return $value;
        }
        if (is_string($value) && preg_match('/^(\d+)/', $value, $matches)) {
            return (int) $matches[1];
        }

        return 0;
    }

    private function getProductDataValue($source, string $key)
    {
        if (is_array($source) && array_key_exists($key, $source)) {
            return $source[$key];
        }
        if ($source instanceof ArrayAccess && $source->offsetExists($key)) {
            return $source[$key];
        }
        if (is_object($source) && isset($source->{$key})) {
            return $source->{$key};
        }

        return null;
    }

    private function isTruthy($value): bool
    {
        return (bool) $value;
    }
}
