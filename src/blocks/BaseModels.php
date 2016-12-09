<?php
namespace rjapi\blocks;

use rjapi\extension\json\api\forms\BaseFormResource;
use rjapi\controllers\YiiTypesController;
use yii\console\Controller;
use yii\helpers\StringHelper;

class BaseModels extends Models
{
    use ContentManager;

    protected $sourceCode = '';
    /** @var YiiTypesController generator */
    private $generator       = null;
    private $additionalProps = [
        'id' => [
            'type' => 'integer',
        ],
    ];

    public function __construct(Controller $generator)
    {
        $this->generator = $generator;
    }

    public function setCodeState(Controller $generator)
    {
        $this->generator = $generator;
    }

    public function create()
    {
        $this->setTag();
        $this->setNamespace(
            $this->generator->modelsFormDir .
            YiiTypesController::BACKSLASH . $this->generator->formsDir
        );

        $baseFullForm = BaseFormResource::class;
        $baseFormName = StringHelper::basename($baseFullForm);
        $this->setUse($baseFullForm);
        $this->startClass(
            YiiTypesController::FORM_BASE .
            YiiTypesController::FORM_PREFIX . $this->generator->objectName, $baseFormName
        );

        if(!empty($this->generator->objectProps[YiiTypesController::RAML_RELATIONSHIPS][YiiTypesController::RAML_TYPE])
           &&
           !empty($this->generator->types[$this->generator->objectProps[YiiTypesController::RAML_RELATIONSHIPS][YiiTypesController::RAML_TYPE]])
        )
        {
            $this->setProps(
                $this->generator->types[$this->generator->objectProps[YiiTypesController::RAML_RELATIONSHIPS][YiiTypesController::RAML_TYPE]]
                [YiiTypesController::RAML_PROPS][YiiTypesController::RAML_DATA][YiiTypesController::RAML_ITEMS]
            );
        }
        else
        {
            $this->setProps();
        }

        $this->constructRules();
        if(!empty($this->generator->objectProps[YiiTypesController::RAML_RELATIONSHIPS]))
        {
            $this->constructRelations($this->generator->objectProps[YiiTypesController::RAML_RELATIONSHIPS]);
        }
        // create closing brace
        $this->endClass();

        $fileForm = $this->generator->formatFormsPath()
                    . YiiTypesController::SLASH
                    . YiiTypesController::FORM_BASE
                    . YiiTypesController::FORM_PREFIX
                    . $this->generator->objectName
                    . YiiTypesController::PHP_EXT;
        FileManager::createFile($fileForm, $this->sourceCode);
    }

    private function setProps($relationTypes = null)
    {
        // additional props
        if(!empty($this->additionalProps))
        {
            foreach($this->additionalProps as $prop => $propVal)
            {
                $this->createProperty($prop, YiiTypesController::PHP_MODIFIER_PUBLIC);
            }
        }

        // properties creation
        $this->sourceCode .= YiiTypesController::TAB_PSR4 . YiiTypesController::COMMENT . ' Attributes' . PHP_EOL;
        foreach($this->generator->types[$this->generator->objectProps[YiiTypesController::RAML_ATTRS]]
        [YiiTypesController::RAML_PROPS] as $propKey => $propVal)
        {
            if(is_array($propVal))
            {
                $this->createProperty($propKey, YiiTypesController::PHP_MODIFIER_PUBLIC);
            }
        }
        $this->sourceCode .= PHP_EOL;

        // related props
        if($relationTypes !== null)
        {
            $this->sourceCode .= YiiTypesController::TAB_PSR4 . YiiTypesController::COMMENT . ' Relations' . PHP_EOL;
            foreach($relationTypes as $attrKey => $attrVal)
            {
                // determine attr
                if($attrKey !== YiiTypesController::RAML_ID && $attrKey !== YiiTypesController::RAML_TYPE)
                {
                    $this->createProperty($attrKey, YiiTypesController::PHP_MODIFIER_PUBLIC);
                }
            }
            $this->sourceCode .= PHP_EOL;
        }
    }

    private function constructRules()
    {
        $this->startMethod(YiiTypesController::PHP_YII_RULES, YiiTypesController::PHP_MODIFIER_PUBLIC, YiiTypesController::PHP_TYPES_ARRAY);
        // attrs validation
        $this->startArray();
        // gather required fields
        $this->setRequired();
        // gather types and constraints
        $this->setTypesAndConstraints();
        $this->endArray();
        $this->endMethod();
    }

    private function setRequired()
    {
        $keysCnt = 0;
        $reqKeys = '';

        if(!empty($this->additionalProps))
        {
            foreach($this->additionalProps as $prop => $propVal)
            {
                if(empty($propVal[YiiTypesController::RAML_REQUIRED]) === false &&
                   (bool) $propVal[YiiTypesController::RAML_REQUIRED] === true
                )
                {
                    if($keysCnt > 0)
                    {
                        $reqKeys .= ', ';
                    }
                    $reqKeys .= '"' . $prop . '"';
                    ++$keysCnt;
                }
            }
        }

        foreach($this->generator->types[$this->generator->objectProps[YiiTypesController::RAML_ATTRS]]
        [YiiTypesController::RAML_PROPS] as $attrKey => $attrVal)
        {
            // determine attr
            if(is_array($attrVal))
            {
                if(isset($attrVal[YiiTypesController::RAML_REQUIRED]) &&
                   (bool) $attrVal[YiiTypesController::RAML_REQUIRED] === true
                )
                {
                    if($keysCnt > 0)
                    {
                        $reqKeys .= ', ';
                    }
                    $reqKeys .= '"' . $attrKey . '"';
                    ++$keysCnt;
                }
            }
        }

        if($keysCnt > 0)
        {
            $this->sourceCode .= YiiTypesController::TAB_PSR4 . YiiTypesController::TAB_PSR4 . YiiTypesController::TAB_PSR4
                                 . YiiTypesController::OPEN_BRACKET . YiiTypesController::OPEN_BRACKET
                                 . $reqKeys . YiiTypesController::CLOSE_BRACKET;
            $this->sourceCode .= ', "' . YiiTypesController::RAML_REQUIRED . '"';
            $this->sourceCode .= YiiTypesController::CLOSE_BRACKET;
            $this->sourceCode .= ', ' . PHP_EOL;
        }
    }

    private function setTypesAndConstraints()
    {
        if(!empty($this->additionalProps))
        {
            foreach($this->additionalProps as $prop => $propVal)
            {
                $this->sourceCode .= YiiTypesController::TAB_PSR4 . YiiTypesController::TAB_PSR4 .
                                     YiiTypesController::TAB_PSR4
                                     . YiiTypesController::OPEN_BRACKET . '"' . $prop . '" ';
                $this->setProperty($propVal);
                $this->sourceCode .= YiiTypesController::CLOSE_BRACKET;
                $this->sourceCode .= ', ' . PHP_EOL;
            }
        }

        $attrsCnt =
            count($this->generator->types[$this->generator->objectProps[YiiTypesController::RAML_ATTRS]][YiiTypesController::RAML_PROPS]);
        foreach($this->generator->types[$this->generator->objectProps[YiiTypesController::RAML_ATTRS]]
        [YiiTypesController::RAML_PROPS] as $attrKey => $attrVal)
        {
            --$attrsCnt;
            // determine attr
            if($attrKey !== YiiTypesController::RAML_TYPE && $attrKey !== YiiTypesController::RAML_REQUIRED &&
               is_array($attrVal)
            )
            {
                $this->sourceCode .= YiiTypesController::TAB_PSR4 . YiiTypesController::TAB_PSR4 .
                                     YiiTypesController::TAB_PSR4
                                     . YiiTypesController::OPEN_BRACKET . '"' . $attrKey . '" ';
                $this->setProperty($attrVal);
                $this->sourceCode .= YiiTypesController::CLOSE_BRACKET;
                if($attrsCnt > 0)
                {
                    $this->sourceCode .= ', ' . PHP_EOL;
                }
            }
        }
    }

    private function constructRelations($relationTypes)
    {
        $this->sourceCode .= PHP_EOL . PHP_EOL;
        $this->startMethod(YiiTypesController::PHP_YII_RELATIONS, YiiTypesController::PHP_MODIFIER_PUBLIC, YiiTypesController::PHP_TYPES_ARRAY);
        // attrs validation
        $this->startArray();
        $rel = empty($relationTypes[YiiTypesController::RAML_TYPE]) ? $relationTypes :
            $relationTypes[YiiTypesController::RAML_TYPE];

        $rels = explode('|', str_replace('[]', '', $rel));
        foreach($rels as $k => $rel)
        {
            $this->setRelations(strtolower(trim(str_replace(YiiTypesController::CUSTOM_TYPES_RELATIONSHIPS, '', $rel))));
            if(!empty($rels[$k + 1]))
            {
                $this->sourceCode .= PHP_EOL;
            }
        }
        $this->endArray();
        $this->endMethod();
    }

    private function setRelations($relationTypes)
    {
        $this->sourceCode .= YiiTypesController::TAB_PSR4 . YiiTypesController::TAB_PSR4 . YiiTypesController::TAB_PSR4
                             . '"' . $relationTypes . '",';
    }
}