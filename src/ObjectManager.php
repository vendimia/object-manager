<?php
namespace Vendimia\ObjectManager;

use Closure;
use ReflectionClass;
use ReflectionObject;
use ReflectionFunction;
use ReflectionException;
use ReflectionAttribute;
use ReflectionNamedType;
use LogicException;
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
     * @param string|null $method_name Processed function or method name, just
     *          for error reporting
     * @return array Arguments array with new objects injected.
     * @author Oliver Etchebarne <yo@drmad.org>
     */
    private function processParameters(
        array $params,
        array $args,
        ?string $method_name = null
    ): array
    {
        // Usamos este array para determinar si $args tiene valores de
        // parámetros que no existen
        $used_args = array_flip(array_keys($args));

        foreach ($params as $p) {
            // Removemos el nombre del parámetro de $used_args
            unset($used_args[$p->getName()]);

            // Solo inyectamos argumentos que no sean union, que tengan tipo,
            // que no sean builtin, y que no existan anteriormente en $arg
            $type = $p->getType();
            if ($type instanceof ReflectionNamedType
                && $type->isBuiltin() === false
                && !key_exists($p->getName(), $args)) {

                try {
                    $args[$p->getName()] = $this->get($p->getType()->getName());
                } catch (LogicException $e) {
                    // Si falla la creación de la clase, y este parámetro no
                    // tiene un valor por defecto, fallamos
                    if(!$p->isOptional()) {
                        throw new LogicException(
                            "Failed to get object for parameter '{$p->getName()}' of class '{$p->getType()->getName()}'",
                            previous: $e
                        );
                    }
                }
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

                if ($attr->hasValue()) {
                    $args[$p->getName()] = $attr->getValue();
                }
            }
        }

        // Si queda algún valor en $used_args, fallamos, pues este argumento
        // no tiene un parámetro
        if ($used_args) {
            throw new LogicException("Unknow named parameter(s) for {$method_name}: " . join(', ', array_flip($used_args)));
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
            throw new LogicException("Class constructor arguments must be named only");
        }

        if (!class_exists($identifier)) {
            throw new LogicException("Class or binding '{$identifier}' doesn't exists");
        }

        $rc = new ReflectionClass($identifier);

        if (!$rc->hasMethod('__construct')) {
            // No hacemos nada
            return new $identifier;
        }

        try {
            $args = $this->processParameters(
                $rc->getMethod('__construct')->getParameters(),
                $args,
                $identifier . '::__construct()',
            );
        } catch (LogicException $e) {
            throw new LogicException(
                "Failed to instance new class or binding '{$identifier}'",
                previous: $e
            );
        }

        return new $identifier(...$args);
    }

    /**
     * Calls Closure injecting dependencies.
     */
    public function call(Closure $closure, ...$args)
    {
        if ($args && !$this->hasOnlyStringKeys($args)) {
            throw new LogicException("Arguments must be named only");
        }

        $rf = new ReflectionFunction($closure);
        try {
            $args = $this->processParameters(
                $rf->getParameters(),
                $args,
                $rf->getName() . '()',
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
            throw new LogicException("Arguments must be named only");
        }

        $rc = new ReflectionObject($object);
        $rm = $rc->getMethod($method);
        try {
            $args = $this->processParameters(
                $rm->getParameters(),
                $args,
                $rc->getName() . '->' . $rm->getName() . '()',
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
            throw new LogicException("Arguments must be named only");
        }

        $rc = new ReflectionClass($class);
        $rm = $rc->getMethod($method);
        try {
            $args = $this->processParameters(
                $rm->getParameters(),
                $args,
                $rc->getName() . '::' . $rm->getName(),
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
