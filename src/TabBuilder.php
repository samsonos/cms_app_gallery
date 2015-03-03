<?php
/**
 * Created by Maxim Omelchenko <omelchenko@samsonos.com>
 * on 22.01.2015 at 16:25
 */

namespace samson\cms\web\gallery;

use samson\cms\web\material\FormTab;

class TabBuilder extends FormTab
{

    /** @var bool Flag to determine whether this tab should be rendered automatically */
    public static $AUTO_RENDER = true;


    /**
     * @param \samson\cms\web\material\Form $form
     * @param FormTab $parent
     */
    public function __construct(\samson\cms\web\material\Form & $form, FormTab & $parent = null)
    {
        // Save pointer to Form
        $this->form = & $form;

        // Save pointer to parent FormTab
        $this->parent = & $parent;

        /** @var \samson\activerecord\materialfield $materialField MaterialField object */
        $materialField = null;

        /** @var \samson\activerecord\field $field */
        foreach ($form->fields as $field) {
            // If it is gallery field
            if (!empty($field) && $field->Type == 9) {
                // If there is no materialfield object for gallery
                if (
                !dbQuery('materialfield')
                    ->cond('MaterialID', $this->form->material->id)
                    ->cond('FieldID', $field->id)
                    ->first($materialField)
                ) {
                    // Create materialfield object
                    $materialField = new \samson\activerecord\materialfield(false);
                    $materialField->FieldID = $field->id;
                    $materialField->MaterialID = $this->form->material->id;
                    $materialField->Active = 1;
                    $materialField->save();
                }
                // Create tab
                $tab = new MaterialTab($this->form, $materialField->MaterialFieldID);
                $tab->name = empty($field->Description) ? $field->Name : $field->Description;
                // Add it to form tabs
                $this->form->tabs[] = $tab;
            }
        }
    }
}
