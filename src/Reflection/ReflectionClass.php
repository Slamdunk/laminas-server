<?php

/**
 * @see       https://github.com/laminas/laminas-server for the canonical source repository
 * @copyright https://github.com/laminas/laminas-server/blob/master/COPYRIGHT.md
 * @license   https://github.com/laminas/laminas-server/blob/master/LICENSE.md New BSD License
 */

declare(strict_types=1);

namespace Laminas\Server\Reflection;

use ReflectionClass as PhpReflectionClass;

/**
 * Class/Object reflection
 *
 * Proxies calls to a ReflectionClass object, and decorates getMethods() by
 * creating its own list of {@link Laminas\Server\Reflection\ReflectionMethod}s.
 */
class ReflectionClass
{
    /**
     * Optional configuration parameters; accessible via {@link __get} and
     * {@link __set()}
     * @var array
     */
    protected $config = [];

    /**
     * Array of {@link \Laminas\Server\Reflection\Method}s
     * @var array
     */
    protected $methods = [];

    /**
     * Namespace
     * @var null|string
     */
    protected $namespace;

    /**
     * ReflectionClass object
     * @var PhpReflectionClass
     */
    protected $reflection;

    /**
     * Reflection class name (needed for serialization)
     * @var string
     */
    protected $name;

    /**
     * Constructor
     *
     * Create array of dispatchable methods, each a
     * {@link Laminas\Server\Reflection\ReflectionMethod}. Sets reflection object property.
     *
     * @param PhpReflectionClass $reflection
     * @param string $namespace
     * @param null|array $argv
     */
    public function __construct(PhpReflectionClass $reflection, ?string $namespace = null, ?array $argv = null)
    {
        $this->reflection = $reflection;
        $this->name = $reflection->getName();
        $this->setNamespace($namespace);

        foreach ($reflection->getMethods() as $method) {
            // Don't aggregate magic methods
            if ('__' === substr($method->getName(), 0, 2)) {
                continue;
            }

            if ($method->isPublic()) {
                // Get signatures and description
                $this->methods[] = new ReflectionMethod($this, $method, $this->getNamespace(), $argv);
            }
        }
    }

    /**
     * Proxy reflection calls
     *
     * @param string $method
     * @param array $args
     * @throws Exception\BadMethodCallException
     * @return mixed
     */
    public function __call(string $method, array $args)
    {
        if (method_exists($this->reflection, $method)) {
            return call_user_func_array([$this->reflection, $method], $args);
        }

        throw new Exception\BadMethodCallException('Invalid reflection method');
    }

    /**
     * Retrieve configuration parameters
     *
     * Values are retrieved by key from {@link $config}. Returns null if no
     * value found.
     *
     * @param string $key
     * @return mixed
     */
    public function __get(string $key)
    {
        if (isset($this->config[$key])) {
            return $this->config[$key];
        }
    }

    /**
     * Set configuration parameters
     *
     * Values are stored by $key in {@link $config}.
     *
     * @param string $key
     * @param mixed $value
     * @return void
     */
    public function __set(string $key, $value): void
    {
        $this->config[$key] = $value;
    }

    /**
     * Return array of dispatchable {@link \Laminas\Server\Reflection\ReflectionMethod}s.
     *
     * @access public
     * @return array
     */
    public function getMethods(): array
    {
        return $this->methods;
    }

    /**
     * Get namespace for this class
     *
     * @return null|string
     */
    public function getNamespace(): ?string
    {
        return $this->namespace;
    }

    /**
     * Set namespace for this class
     *
     * @param null|string $namespace
     * @throws Exception\InvalidArgumentException
     * @return void
     */
    public function setNamespace(?string $namespace = null): void
    {
        if (empty($namespace)) {
            $this->namespace = '';
            return;
        }

        if (! preg_match('/[a-z0-9_\.]+/i', $namespace)) {
            throw new Exception\InvalidArgumentException('Invalid namespace');
        }

        $this->namespace = $namespace;
    }

    /**
     * Wakeup from serialization
     *
     * Reflection needs explicit instantiation to work correctly. Re-instantiate
     * reflection object on wakeup.
     *
     * @return void
     */
    public function __wakeup(): void
    {
        $this->reflection = new PhpReflectionClass($this->name);
    }

    /**
     * @return string[]
     */
    public function __sleep(): array
    {
        return ['config', 'methods', 'namespace', 'name'];
    }
}
