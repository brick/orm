<?php

declare(strict_types=1);

namespace Brick\ORM;

class ProxyBuilder
{
    /**
     * @var string|null
     */
    private $proxyNamespace;

    /**
     * @var string|null
     */
    private $entityClassName;

    /**
     * @var array|null
     */
    private $nonIdProps;

    /**
     * @param string $namespace The namespace of the proxy.
     *
     * @return void
     */
    public function setProxyNamespace(string $namespace) : void
    {
        $this->proxyNamespace = $namespace;
    }

    /**
     * @param string $className The FQCN of the entity.
     *
     * @return void
     */
    public function setEntityClassName(string $className) : void
    {
        $this->entityClassName = $className;
    }

    /**
     * @param array $props The list of non-identity properties.
     *
     * @return void
     */
    public function setNonIdProps(array $props) : void
    {
        $this->nonIdProps = $props;
    }

    /**
     * Builds and returns the proxy source code.
     *
     * @return string
     *
     * @throws \RuntimeException If data are missing.
     * @throws \ReflectionException If a class does not exist.
     */
    public function build() : string
    {
        $checks = [
            $this->proxyNamespace,
            $this->entityClassName,
            $this->nonIdProps
        ];

        foreach ($checks as $check) {
            if ($check === null) {
                throw new \RuntimeException('Missing data to build proxy.');
            }
        }

        $imports = [
            $this->entityClassName
        ];

        $entityClassShortName = (new \ReflectionClass($this->entityClassName))->getShortName();

        $code = file_get_contents(__DIR__ . '/ProxyTemplate.php');

        $code = str_replace('PROXY_NAMESPACE', $this->proxyNamespace, $code);
        $code = str_replace('CLASS_NAME', $entityClassShortName, $code);

        if ($this->nonIdProps) {
            $unsets = "\n";

            $unsets .= implode(",\n", array_map(function(string $prop) : string {
                return str_repeat(' ', 12) . '$this->' . $prop;
            }, $this->nonIdProps));

            $unsets .= "\n" . str_repeat(' ' , 8);

            $code = str_replace('$UNSET_NON_ID_PROPS', $unsets, $code);
        } else {
            $code = str_replace('unset($UNSET_NON_ID_PROPS);', '', $code);
        }

        // Imports

        $importString = '';

        $imports = array_values(array_unique($imports));

        foreach ($imports as $key => $import) {
            if ($key !== 0) {
                $importString .= '    ';
            }

            $importString .= $import;

            if ($key !== count($imports) - 1) {
                $importString .= ",\n";
            }
        }

        $code = str_replace('IMPORTS', $importString, $code);

        return $code;
    }
}