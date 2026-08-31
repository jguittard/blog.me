<?php

declare(strict_types=1);

namespace Admin\Form;

use Laminas\Filter\StringTrim;
use Laminas\Filter\StripTags;
use Laminas\Form\Element\Submit;
use Laminas\Form\Element\Text;
use Laminas\Form\Form;
use Laminas\InputFilter\InputFilter;
use Laminas\Validator\NotEmpty;
use Laminas\Validator\StringLength;

final class TagForm extends Form
{
    private const string INPUT = 'w-full rounded-md border border-gray-300 px-3 py-2 text-sm '
        . 'shadow-sm focus:border-primary focus:outline-none focus:ring-1 focus:ring-primary';

    public function __construct()
    {
        parent::__construct('tag');

        $this->add([
            'name'       => 'name',
            'type'       => Text::class,
            'options'    => ['label' => 'Name'],
            'attributes' => ['class' => self::INPUT, 'required' => true, 'maxlength' => 80],
        ]);
        $this->add([
            'name'       => 'submit',
            'type'       => Submit::class,
            'attributes' => ['value' => 'Save'],
        ]);

        $filter = new InputFilter();
        $filter->add([
            'name'       => 'name',
            'required'   => true,
            'filters'    => [['name' => StringTrim::class], ['name' => StripTags::class]],
            'validators' => [
                ['name' => NotEmpty::class],
                ['name' => StringLength::class, 'options' => ['min' => 1, 'max' => 80]],
            ],
        ]);

        $this->setInputFilter($filter);
    }
}
