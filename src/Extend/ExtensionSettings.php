<?php

namespace FoF\Extend\Extend;

use Flarum\Extend\ExtenderInterface;
use Flarum\Frontend\Document;
use Illuminate\Contracts\Container\Container;
use Flarum\Extension\Extension;
use Flarum\Frontend\Frontend;
use Flarum\Settings\SettingsRepositoryInterface;

class ExtensionSettings implements ExtenderInterface
{
    /**
     * @var SettingsRepositoryInterface
     */
    private $settings;

    /**
     * @var string
     */
    private $prefix = null;

    /**
     * @var array
     */
    private $keys = [];

    /**
     * @param string $frontend
     */
    public function __construct() {
        $this->settings = app(SettingsRepositoryInterface::class);
    }

    public function extend(Container $container, Extension $extension = null) {
        $container->resolving(
            "flarum.frontend.forum",
            function (Frontend $frontend, Container $container) {
                $frontend->content(function (Document $document) {
                    foreach ($this->keys as $key) {
                        $document->payload[$key] = $this->settings->get($key);
                    }
                });
            }
        );
    }

    /**
     * Set extension keys prefix
     *
     * @param string $prefix
     * @return self
     */
    public function setPrefix($prefix) : self {
        $this->prefix = $prefix;

        return $this;
    }

    /**
     * Add setting key
     *
     * @param string $key
     * @return self
     */
    public function addKey($key) : self {
        $this-addKeys(array($key));

        return $this;
    }

    /**
     * Add multiple setting keys
     *
     * @param array $keys
     * @return self
     */
    public function addKeys(array $keys) : self {
        if (isset($this->prefix)) {
            $keys = array_map(function ($key) {
                return $this->prefix.$key;
            }, $keys);
        }
        
        $this->keys = $keys;

        return $this;
    }
}