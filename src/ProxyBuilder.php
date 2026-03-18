<?php

declare(strict_types=1);

namespace Brick\ORM;

use ReflectionClass;
use ReflectionException;
use RuntimeException;

use function array_map;
use function array_unique;
use function array_values;
use function count;
use function file_get_contents;
use function implode;
use function str_repeat;
use function str_replace;
use function var_export;

class ProxyBuilder
{
    private null|string $proxyNamespace = null;

    /**
     * @var class-string|null
     */
    private null|string $entityClassName = null;

    /**
     * @var list<string>|null
     */
    private null|array $nonIdProps = null;

    /**
     * @param string $namespace The namespace of the proxy class.
     */
    public function setProxyNamespace(string $namespace): void
    {
        $this->proxyNamespace = $namespace;
    }

    /**
     * @param class-string $className The FQCN of the entity.
     */
    public function setEntityClassName(string $className): void
    {
        $this->entityClassName = $className;
    }

    /**
     * @param list<string> $props The list of non-identity properties.
     */
    public function setNonIdProps(array $props): void
    {
        $this->nonIdProps = $props;
    }

    /**
     * Builds and returns the proxy source code.
     *
     * @throws RuntimeException    If data are missing.
     * @throws ReflectionException If a class does not exist.
     */
    public function build(): string
    {
        if ($this->proxyNamespace === null) {
            throw new RuntimeException('Missing proxy namespace.');
        }

        if ($this->entityClassName === null) {
            throw new RuntimeException('Missing entity class name.');
        }

        if ($this->nonIdProps === null) {
            throw new RuntimeException('Missing non-id props.');
        }

        $imports = [
            $this->entityClassName,
        ];

        $entityClassShortName = (new ReflectionClass($this->entityClassName))->getShortName();

        $code = file_get_contents(__DIR__ . '/ProxyTemplate.php');

        $code = str_replace('PROXY_NAMESPACE', $this->proxyNamespace, $code);
        $code = str_replace('CLASS_NAME', $entityClassShortName, $code);

        if ($this->nonIdProps) {
            $unsets = "\n";

            $unsets .= implode(",\n", array_map(static function (string $prop): string {
                return str_repeat(' ', 12) . '$this->' . $prop;
            }, $this->nonIdProps));

            $unsets .= "\n" . str_repeat(' ', 8);

            $code = str_replace('$UNSET_NON_ID_PROPS', $unsets, $code);
        } else {
            $code = str_replace('unset($UNSET_NON_ID_PROPS);', '', $code);
        }

        $nonIdProps = array_map(static function (string $prop): string {
            return var_export($prop, true);
        }, $this->nonIdProps);

        $code = str_replace('NON_ID_PROPS', implode(', ', $nonIdProps), $code);

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
