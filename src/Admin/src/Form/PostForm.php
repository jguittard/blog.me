<?php

declare(strict_types=1);

namespace Admin\Form;

use App\Domain\Value\PostStatus;
use Laminas\Filter\StringTrim;
use Laminas\Filter\StripTags;
use Laminas\Filter\ToNull;
use Laminas\Form\Element\Select;
use Laminas\Form\Element\Submit;
use Laminas\Form\Element\Text;
use Laminas\Form\Element\Textarea;
use Laminas\Form\Element\Url;
use Laminas\Form\Form;
use Laminas\InputFilter\InputFilter;
use Laminas\Validator\Date;
use Laminas\Validator\InArray;
use Laminas\Validator\NotEmpty;
use Laminas\Validator\StringLength;
use Laminas\Validator\Uri;

use function array_keys;
use function array_map;

final class PostForm extends Form
{
    public const string PUBLISHED_AT_FORMAT = 'Y-m-d\TH:i';

    private const string INPUT = 'w-full rounded-md border border-gray-300 px-3 py-2 text-sm '
        . 'shadow-sm focus:border-primary focus:outline-none focus:ring-1 focus:ring-primary';

    /** @param array<string, string> $categoryOptions id => name */
    public function __construct(private readonly array $categoryOptions = [])
    {
        parent::__construct('post');

        $statusOptions = [];
        foreach (PostStatus::cases() as $case) {
            $statusOptions[$case->value] = $case->label();
        }

        $this->add([
            'name'       => 'title',
            'type'       => Text::class,
            'options'    => ['label' => 'Title'],
            'attributes' => ['class' => self::INPUT, 'required' => true, 'maxlength' => 200],
        ]);
        $this->add([
            'name'       => 'categoryId',
            'type'       => Select::class,
            'options'    => [
                'label'         => 'Category',
                'empty_option'  => '— none —',
                'value_options' => $this->categoryOptions,
            ],
            'attributes' => ['class' => self::INPUT],
        ]);
        $this->add([
            'name'       => 'status',
            'type'       => Select::class,
            'options'    => ['label' => 'Status', 'value_options' => $statusOptions],
            'attributes' => ['class' => self::INPUT],
        ]);
        $this->add([
            'name'       => 'publishedAt',
            'type'       => Text::class,
            'options'    => ['label' => 'Publish date (when published)'],
            'attributes' => ['class' => self::INPUT, 'type' => 'datetime-local'],
        ]);
        $this->add([
            'name'       => 'excerpt',
            'type'       => Textarea::class,
            'options'    => ['label' => 'Excerpt'],
            'attributes' => ['class' => self::INPUT, 'rows' => 2],
        ]);
        $this->add([
            'name'       => 'body',
            'type'       => Textarea::class,
            'options'    => ['label' => 'Body'],
            'attributes' => ['class' => self::INPUT, 'rows' => 14, 'required' => true],
        ]);
        $this->add([
            'name'       => 'tags',
            'type'       => Text::class,
            'options'    => ['label' => 'Tags (comma separated)'],
            'attributes' => ['class' => self::INPUT],
        ]);
        $this->add([
            'name'       => 'imageUrl',
            'type'       => Url::class,
            'options'    => ['label' => 'Cover image URL'],
            'attributes' => ['class' => self::INPUT],
        ]);
        $this->add([
            'name'       => 'imageAlt',
            'type'       => Text::class,
            'options'    => ['label' => 'Cover image alt text'],
            'attributes' => ['class' => self::INPUT],
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
            'name'       => 'title',
            'required'   => true,
            'filters'    => [['name' => StringTrim::class], ['name' => StripTags::class]],
            'validators' => [
                ['name' => NotEmpty::class],
                ['name' => StringLength::class, 'options' => ['min' => 1, 'max' => 200]],
            ],
        ]);
        $filter->add([
            'name'       => 'body',
            'required'   => true,
            'filters'    => [['name' => StringTrim::class]],
            'validators' => [['name' => NotEmpty::class]],
        ]);
        $filter->add([
            'name'        => 'excerpt',
            'required'    => false,
            'allow_empty' => true,
            'filters'     => [
                ['name' => StringTrim::class],
                ['name' => StripTags::class],
                ['name' => ToNull::class],
            ],
            'validators'  => [['name' => StringLength::class, 'options' => ['max' => 500]]],
        ]);
        $filter->add([
            'name'       => 'status',
            'required'   => true,
            'validators' => [
                ['name' => NotEmpty::class],
                [
                    'name'    => InArray::class,
                    'options' => ['haystack' => array_map(static fn ($c) => $c->value, PostStatus::cases())],
                ],
            ],
        ]);

        $categoryValidators = [];
        if ($this->categoryOptions !== []) {
            $categoryValidators[] = [
                'name'    => InArray::class,
                'options' => ['haystack' => array_keys($this->categoryOptions)],
            ];
        }
        $filter->add([
            'name'        => 'categoryId',
            'required'    => false,
            'allow_empty' => true,
            'filters'     => [['name' => ToNull::class]],
            'validators'  => $categoryValidators,
        ]);
        $filter->add([
            'name'        => 'publishedAt',
            'required'    => false,
            'allow_empty' => true,
            'filters'     => [['name' => StringTrim::class], ['name' => ToNull::class]],
            'validators'  => [
                ['name' => Date::class, 'options' => ['format' => self::PUBLISHED_AT_FORMAT]],
            ],
        ]);
        $filter->add([
            'name'        => 'imageUrl',
            'required'    => false,
            'allow_empty' => true,
            'filters'     => [['name' => StringTrim::class], ['name' => ToNull::class]],
            'validators'  => [['name' => Uri::class, 'options' => ['allowRelative' => false]]],
        ]);
        $filter->add([
            'name'        => 'imageAlt',
            'required'    => false,
            'allow_empty' => true,
            'filters'     => [['name' => StringTrim::class], ['name' => StripTags::class], ['name' => ToNull::class]],
        ]);
        $filter->add([
            'name'        => 'tags',
            'required'    => false,
            'allow_empty' => true,
            'filters'     => [['name' => StringTrim::class]],
        ]);

        return $filter;
    }
}
