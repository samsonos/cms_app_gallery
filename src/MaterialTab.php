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
    public $name = 'Галлерея';

    /** HTML identifier */
    public $id = 'gallery-tab';

    /** Tab sorting index */
    public $index = 4;

    /** @see \samson\cms\web\material\FormTab::content() */
    public function content()
    {
        // Render content into inner content html
        if (isset($this->form->material)) {
            $this->content_html = m('gallery')->html_list($this->form->material->id);
        }

        // Render parent tab view
        return parent::content();
    }
}
