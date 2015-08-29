<?php
/**
 * Created by PhpStorm.
 * User: egorov
 * Date: 11.04.2015
 * Time: 9:58
 */
namespace samsoncms\app\gallery;

use samsonframework\core\RenderInterface;
use samsonframework\orm\QueryInterface;
use samsonframework\pager\PagerInterface;
use samsoncms\field\Generic;
use samsoncms\field\Control;

/**
 * Collection of SamsonCMS gallery images
 * @package samsoncms\app\gallery
 */
class Collection extends \samsoncms\Collection
{
    /** @var string Index collection view */
    protected $indexView = 'collection/index';

    /** @var string Entity fields row view */
    protected $rowView = 'collection/item';

    /**
     * Overload default constructor
     * @param RenderInterface $renderer View renderer
     * @param QueryInterface $query Database query
     * @param PagerInterface $pager Paging
     */
    public function __construct(RenderInterface $renderer, QueryInterface $query, PagerInterface $pager)
    {
        // Fill collection fields
        $this->fields = array(
            new Generic('id', '#', 0, 'id', false),
            new Generic('name', '#', 0, 'id', false),
            new Generic('size', '#', 0, 'id', false),
            new Control(),
        );

        // Call parent
        parent::__construct($renderer, $query, $pager);

        // Apply sorting by identifier
        $this->sorter($this->entityPrimaryField, 'DESC');

        // Fill collection on creation
        $this->fill();
    }
}
