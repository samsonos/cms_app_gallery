<?php
namespace samson\cms\web\gallery;

use samson\cms\web\material\FormTab;

/**
 * Gallery Tab for CMSMaterial form 
 *
 * @author Egorov Vitaly <egorov@samsonos.com>
 */
class MaterialTab extends FormTab
{
    /** Tab name for showing in header */
    public $name;

    /** HTML identifier */
    public $id = 'gallery';

    public $galleryId;

    /** @var bool Flag to determine whether this tab should be rendered automatically */
    public static $AUTO_RENDER = false;

    /** Tab sorting index */
    public $index = 4;

    /**
     * @param \samson\cms\web\material\Form $form
     * @param int $galleryId
     */
    public function __construct(&$form, $galleryId)
    {
        // Save gallery Identifier
        $this->galleryId = $galleryId;
        // Set gallery HTML id
        $this->id = $this->id . '_' . $galleryId . '-tab';
        // Save pointer to Form
        $this->form = & $form;
        // Add tab to list
        $this->tabs[] = $this;
    }

    /** @see \samson\cms\web\material\FormTab::content() */
    public function content()
    {
        // Render content into inner content html
        if (isset($this->form->material)) {
            /** @var \samson\cms\web\gallery\App $galleryApp */
            $galleryApp = m('gallery');
            $this->content_html = $galleryApp->getHTML($this->galleryId);
        }

        // Render parent tab view
        return parent::content();
    }
}
