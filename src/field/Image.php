<?php
/**
 * Created by PhpStorm.
 * User: egorov
 * Date: 21.04.2015
 * Time: 14:04
 */
namespace samsoncms\app\gallery\field;

use samsonframework\core\RenderInterface;
use samsonframework\orm\QueryInterface;
use samsoncms\field\Generic;

/**
 * Collection view additional field class
 * @package samsoncms
 * @author Vitaly Iegorov <egorov@samsonos.com>
 */
class Image extends Generic
{
    /** @var string Path to field inner image */
    protected $inputView = 'field/image';

    /**  Overload parent constructor and pass needed params there */
    public function __construct()
    {
        // Create object instance with fixed parameters
        parent::__construct('image', t('Картинка', true), 0, 'image', false);
    }

    /**
     * Render collection entity field inner block
     * @param RenderInterface $renderer
     * @param QueryInterface $query
     * @param mixed $object Entity object instance
     * @return string Rendered entity field
     */
    public function render(RenderInterface $renderer, QueryInterface $query, $object)
    {
        // Get old-way image path, remove full path to check file
        if (empty($object->Path)) {
            $path = $object->Src;
        } else { // Use new CORRECT way
            $path = $object->Path . $object->Src;
        }

        // form relative path to the image
        $dir = quotemeta(__SAMSON_BASE__);
        // TODO: WTF? Why do we need this, need comments!!!
        if (strpos($path, 'http://') === false) {
            if ($dir == '/') {
                return substr($path, 1);
            } else {
                return preg_replace('/' . addcslashes($dir, '/') . '/', '', $path);
            }
        }

        $html = $renderer
            ->view($this->inputView)
            ->set('path', $path)
            ->set('title', $object->Name)
            ->output();

        // Render input field view
        return $renderer
            ->view($this->innerView)
            ->set('class', $this->css)
            ->set('field_html', $html)
            ->output();
    }
}
