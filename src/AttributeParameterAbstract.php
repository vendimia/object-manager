<?php
namespace Vendimia\ObjectManager;

/**
 * PHP Attributes used to generate a parameter value
 */
abstract class AttributeParameterAbstract
{
    protected $name = null;

    /**
     * Sets the parameter name affected by this attribute, only if it is null.
     */
    public function setDefaultName($name) {
        if (is_null($this->name)) {
            $this->name = $name;
        }
    }

    /**
     * Returns the value used as argument.
     */
    abstract public function getValue();

}
