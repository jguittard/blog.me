<?php

declare(strict_types=1);

namespace Admin\Form;

use Laminas\Filter\StringTrim;
use Laminas\Filter\StripTags;
use Laminas\Filter\ToNull;
use Laminas\Form\Element\Submit;
use Laminas\Form\Element\Text;
use Laminas\Form\Element\Textarea;
use Laminas\Form\Form;
use Laminas\InputFilter\InputFilter;
use Laminas\Validator\NotEmpty;
use Laminas\Validator\StringLength;

final class CategoryForm extends Form
{
    private const string INPUT = 'w-full rounded-md border border-gray-300 px-3 py-2 text-sm '
        . 'shadow-sm focus:border-primary focus:outline-none focus:ring-1 focus:ring-primary';

    public function __construct()
    {
        parent::__construct('category');

        $this->add([
            'name'       => 'name',
            'type'       => Text::class,
            'options'    => ['label' => 'Name'],
            'attributes' => ['class' => self::INPUT, 'required' => true, 'maxlength' => 120],
        ]);
        $this->add([
            'name'       => 'description',
            'type'       => Textarea::class,
            'options'    => ['label' => 'Description'],
            'attributes' => ['class' => self::INPUT, 'rows' => 3],
        ]);
        $this->add([
            'name'       => 'submit',
            'type'       => Submit::class,
            'attributes' => ['value' => 'Save'],
        ]);

        $this->setInputFilter($this->buildInputFilter());
    }

    private function buildInputFilter(): InputFilter
    {
        $filter = new InputFilter();

        $filter->add([
            'name'        => 'name',
            'required'    => true,
            'filters'     => [['name' => StringTrim::class], ['name' => StripTags::class]],
            'validators'  => [
                ['name' => NotEmpty::class],
                ['name' => StringLength::class, 'options' => ['min' => 1, 'max' => 120]],
            ],
        ]);
        $filter->add([
            'name'              => 'description',
            'required'          => false,
            'allow_empty'       => true,
            'filters'           => [
                ['name' => StringTrim::class],
                ['name' => StripTags::class],
                ['name' => ToNull::class],
            ],
            'validators'        => [
                ['name' => StringLength::class, 'options' => ['max' => 500]],
            ],
        ]);

        return $filter;
    }
}
