<?php
namespace Vendimia\ObjectManager;

use Closure;
use ReflectionClass;
use ReflectionObject;
use ReflectionFunction;
use ReflectionException;
use ReflectionAttribute;
use InvalidArgumentException;

/**
 * Dependency injector and object storage.
 *
 * @author Oliver Etchebarne <yo@drmad.org>
 */
class ObjectManager
{
    /** Class aliases */
    private $aliases = [];

    /** Object storage */
    private $storage = [];

    /** 
     * Save ourself in the object storage.
     */
    public function __construct()
    {
        $this->save($this);
    }

    /** 
     * Returns whether an array has only named keys
     */
    private function hasOnlyStringKeys(array $array): bool
    {
        return count(array_filter(array_keys($array), 'is_numeric')) == 0;
    }

    /** 
     * Process an array of ReflectionParameters to inject objects as needed.
     * 
     * @param array $params Array of ReflectionParameters.
     * @param array $args Original arguments array.
     * @return array Argument array with new objects injected.
     * @author Oliver Etchebarne <yo@drmad.org>
     */
    private function processParameters(array $params, array $args): array
    {
        foreach ($params as $p) {
            // Solo inyectamos argumentos que tengan tipo y no sea builtin
            if ($p->getType()?->isBuiltin() === false) {
                $args[$p->getName()] = $this->get($p->getType()->getName());
            }

            // Si tiene atributos tipo AttributeParameterAbstract, los 
            // ejecutamos.
            $attrs = $p->getAttributes(
                AttributeParameterAbstract::class,
                ReflectionAttribute::IS_INSTANCEOF
            );
            foreach($attrs as $ra) {
                $attr = $this->new($ra->getName(), ...$ra->getArguments());
                $attr->setName($p->getName());

                $args[$p->getName()] = $attr->getValue();
            }
        }

        return $args;
    }

    /**
     * Instances a new object.
     *
     * @param string $class_name Class name or alias to instantiate.
     * @param mixed $args Named arguments for the class constructor.
     * @return object Instantated object.
     * @author Oliver Etchebarne <yo@drmad.org>
     */
    public function new(string $class_name, ...$args): object
    {
        if (key_exists($class_name, $this->aliases)) {
            $class_name = $this->aliases[$class_name];
        }

        if ($args && !$this->hasOnlyStringKeys($args)) {
            throw new InvalidArgumentException("Class constructor arguments must be named only.");
        }

        if (!class_exists($class_name)) {
            throw new InvalidArgumentException("Class or binding '{$class_name}' doesn't exists.");
        }

        $rc = new ReflectionClass($class_name);

        if (!$rc->hasMethod('__construct')) {
            // No hacemos nada
            return new $class_name;
        }

        $args = $this->processParameters(
            $rc->getMethod('__construct')->getParameters(),
            $args,
        );

        return new $class_name(...$args);
    }

    /**
     * Executes a Closure injecting dependencies.
     */
    public function call(Closure $closure, ...$args)
    {
        if ($args && !$this->hasOnlyStringKeys($args)) {
            throw new InvalidArgumentException("Arguments must be named only.");
        }

        $rf = new ReflectionFunction($closure);
        try {
            $args = $this->processParameters(
                $rf->getParameters(),
                $args,
            );
        } catch (ReflectionException $e) {
            // Nada...
        }

        return $rf->invokeArgs($args);
    }

    public function callMethod($object, $method, ...$args)
    {
        if ($args && !$this->hasOnlyStringKeys($args)) {
            throw new InvalidArgumentException("Arguments must be named only.");
        }

        $rc = new ReflectionObject($object);
        $rm = $rc->getMethod($method);
        try {
            $args = $this->processParameters(
                $rm->getParameters(),
                $args,
            );
        } catch (ReflectionException $e) {
            // Nada...
        }

        return $rm->invokeArgs($object, $args);
    }

    /**
     * Binds a interface or an alias to a class.
     */
    public function bind(string $alias, string $class)
    {
        $this->aliases[$alias] = $class;
    }

    /**
     * Saves an object into the storage
     * 
     * @author Oliver Etchebarne <yo@drmad.org>
     */
    public function save($object, $name = null): object
    {
        if (!$name) {
            $rc = new ReflectionClass($object);
            $name = $rc->getName();
        }
        $this->storage[$name] = $object;

        return $object;
    }

    /**
     * Returns an object from the storage, builds it if doesn't exists
     * 
     * @author Oliver Etchebarne <yo@drmad.org>
     */
    public function get(string $class_name, ...$args): object
    {
        if (key_exists($class_name, $this->storage)) {
            return $this->storage[$class_name];
        } else {
            return $this->save($this->new($class_name, ...$args));
        }
    }
}
