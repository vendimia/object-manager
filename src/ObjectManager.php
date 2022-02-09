<?php
namespace Vendimia\ObjectManager;

use Closure;
use ReflectionClass;
use ReflectionObject;
use ReflectionFunction;
use ReflectionException;
use ReflectionAttribute;
use ReflectionNamedType;
use InvalidArgumentException;
use Psr\Container\ContainerInterface;

/**
 * Dependency injector and object storage.
 *
 * @author Oliver Etchebarne <yo@drmad.org>
 */
class ObjectManager implements ContainerInterface
{
    /** Class aliases */
    private $aliases = [];

    /** Object storage */
    private $storage = [];

    /** Instance of this class for static access */
    static private ?self $self = null;

    /**
     * Save ourself in the object storage.
     */
    public function __construct()
    {
        $this->save($this);
        self::$self = $this;
    }

    /**
     * Statically retrieve a instance of the object manager.
     *
     * If there is one already created, this method returns it. Otherwise
     * creates a new instance
     */
    public static function retrieve()
    {
        if (self::$self) {
            return self::$self;
        }

        return new self;
    }

    /**
     * Returns whether an array has only named keys.
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
     * @return array Arguments array with new objects injected.
     * @author Oliver Etchebarne <yo@drmad.org>
     */
    private function processParameters(array $params, array $args): array
    {
        foreach ($params as $p) {
            // Solo inyectamos argumentos que no sean union, que tengan tipo
            // y no sea builtin.
            if ($p->getType() instanceof ReflectionNamedType
                && $p->getType()?->isBuiltin() === false) {
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
                $attr->setDefaultName($p->getName());

                $args[$p->getName()] = $attr->getValue();
            }
        }

        return $args;
    }

    /**
     * Instances a new object.
     *
     * @param string $identifier Class name or alias to instantiate.
     * @param mixed $args Named arguments for the class constructor.
     * @return object Instantated object.
     * @author Oliver Etchebarne <yo@drmad.org>
     */
    public function new(string $identifier, ...$args): object
    {
        if (key_exists($identifier, $this->aliases)) {
            $identifier = $this->aliases[$identifier];
        }

        if ($args && !$this->hasOnlyStringKeys($args)) {
            throw new InvalidArgumentException("Class constructor arguments must be named only.");
        }

        if (!class_exists($identifier)) {
            throw new InvalidArgumentException("Class or binding '{$identifier}' doesn't exists.");
        }

        $rc = new ReflectionClass($identifier);

        if (!$rc->hasMethod('__construct')) {
            // No hacemos nada
            return new $identifier;
        }

        $args = $this->processParameters(
            $rc->getMethod('__construct')->getParameters(),
            $args,
        );

        return new $identifier(...$args);
    }

    /**
     * Calls Closure injecting dependencies.
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
        } catch (ReflectionException) {
            // Nada...
        }

        return $rf->invokeArgs($args);
    }

    /**
     * Calls a method from an object, injecting dependencies.
     */
    public function callMethod(object $object, $method, ...$args)
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
     * Calls a static method from a class, injecting dependencies.
     */
    public function callStaticMethod(string $class, $method, ...$args)
    {
        if ($args && !$this->hasOnlyStringKeys($args)) {
            throw new InvalidArgumentException("Arguments must be named only.");
        }

        $rc = new ReflectionClass($class);
        $rm = $rc->getMethod($method);
        try {
            $args = $this->processParameters(
                $rm->getParameters(),
                $args,
            );
        } catch (ReflectionException $e) {
            // Nada...
        }

        return $rm->invokeArgs(null, $args);
    }


    /**
     * Binds a interface or an alias to a class.
     */
    public function bind(string $alias, string $class)
    {
        $this->aliases[$alias] = $class;
    }


    /**
     * Builds and save a new object
     */
    public function build($identifier, ...$args)
    {
        return $this->save($this->new($identifier, ...$args), $identifier);
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
     * Returns an object from the storage, builds it if doesn't exists.
     *
     * @author Oliver Etchebarne <yo@drmad.org>
     */
    public function get(string $identifier, ...$args): object
    {
        if (key_exists($identifier, $this->storage)) {
            return $this->storage[$identifier];
        } else {
            return $this->new($identifier, ...$args);
        }
    }

    /**
     * Returns if an object can be instantiated
     */
    public function has(string $identifier): bool
    {
        if (key_exists($identifier, $this->storage) ||
            class_exists($identifier)) {
            return true;
        }

        return false;
    }
}
