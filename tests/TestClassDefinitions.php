<?php
use Vendimia\ObjectManager\AttributeParameterAbstract;

class Simple
{
}

class Complex
{
}

interface PersonInterface
{
}

interface CarInterface
{
    public function __construct(PersonInterface $chauffeur);
}

class Car implements CarInterface
{
    public function __construct(private PersonInterface $chauffeur)
    {
    }

    public function getChauffeur(): PersonInterface
    {
        return $this->chauffeur;
    }
}

class Toyota extends Car
{
}

class Mazda extends Car
{
}

class Bob implements PersonInterface
{
}

class Alice implements PersonInterface
{
}

class Double extends AttributeParameterAbstract
{
    public function __construct(
        string $name = null,
    )
    {
        if (!is_null($name)) {
            $this->name = $name;
        }
    }

    public function getValue()
    {
        return $this->name . $this->name;
    }
}

class WakaWaka
{
    public function __construct(#[Double] private $Eh)
    {
    }

    public function get()
    {
        return $this->Eh;
    }

    public function zangalewa(#[Double] $Eh)
    {
        return $Eh;
    }
}
