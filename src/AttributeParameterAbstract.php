<?php
namespace Vendimia\ObjectManager;

/**
 * Attributes used to generate a parameter value
 */
abstract class AttributeParameterAbstract
{
    protected $name;
    
    /**
     * Sets the parameter name affected by this attribute
     */
    public function setName($name) {
        $this->name = $name;
    }

    /**
     * Returns the value used as parameter in a function or method call.
     */
    abstract public function getValue();
}
