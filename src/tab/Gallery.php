<?php
/**
 * Created by PhpStorm.
 * User: onysko
 * Date: 10.06.2015
 * Time: 16:43
 */

namespace samsoncms\app\gallery\tab;

use samson\activerecord\field;
use samson\cms\Navigation;
use samson\pager\Pager;
use samsoncms\form\tab\Generic;
use samsonframework\core\RenderInterface;
use samsonframework\orm\QueryInterface;
use samsonframework\orm\Record;

/**
 * SamsonCMS material form gallery tab
 * @package samsoncms\app\gallery\tab
 */
class Gallery extends Generic
{
    /** @var string Tab name or identifier */
    protected $name = 'Галлерея';

    /** @var string HTML tab identifier*/
    protected $id = 'gallery_tab';

    /** @var \samson\activerecord\materialfield Pointer to gallery additional field record */
    public $materialField;

    /** @inheritdoc */
    public function __construct(RenderInterface $renderer, QueryInterface $query, Record $entity, field $field)
    {
        // Check if material have this gallery additional field stored
        if (!$query->className('materialfield')
                ->cond('MaterialID', $entity->id)
                ->cond('FieldID', $field->id)
                ->first($this->materialField)) {
            // Create materialfield object
            $this->materialField = new \samson\activerecord\materialfield(false);
            $this->materialField->FieldID = $field->id;
            $this->materialField->MaterialID = $entity->id;
            $this->materialField->Active = 1;
            $this->materialField->save();
        }

        // Form tab name
        $this->name = t(isset($field->Name{0}) ? $field->Name : $this->name, true);

        // Call parent constructor to define all class fields
        parent::__construct($renderer, $query, $entity);
    }

    /** @inheritdoc */
    public function content()
    {
        $collection = new \samsoncms\app\gallery\Collection($this->renderer, $this->query->className('\samson\activerecord\gallery'), new Pager());

        return $this->renderer->view($this->contentView)->set('content', $collection->render())->output();
    }
}
