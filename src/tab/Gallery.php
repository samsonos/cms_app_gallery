<?php
/**
 * Created by PhpStorm.
 * User: onysko
 * Date: 10.06.2015
 * Time: 16:43
 */

namespace samsoncms\app\gallery\tab;


use samson\cms\Navigation;
use samsoncms\form\tab\Generic;
use samsonframework\core\RenderInterface;
use samsonframework\orm\QueryInterface;
use samsonframework\orm\Record;

class Gallery extends Generic
{
    /** @var string Tab name or identifier */
    protected $name = 'Gallery Tab';

    protected $id = 'gallery_tab';

    public $materialField;

    /** @inheritdoc */
    public function __construct(RenderInterface $renderer, QueryInterface $query, Record $entity, $field)
    {
        if (!dbQuery('materialfield')
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

        $this->name .= ' '.$field->Name;

        // Call parent constructor to define all class fields
        parent::__construct($renderer, $query, $entity);
    }

    /** @inheritdoc */
    public function content()
    {
        $content = $this->renderer->getHTML($this->materialField->id);

        return $this->renderer->view($this->contentView)->content($content)->output();
    }
}
