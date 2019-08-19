<?php

declare(strict_types=1);

namespace Brick\ORM;

class RepositoryBuilder
{
    private ?string $repositoryNamespace = null;

    private ?string $entityClassName = null;

    private ?array $identityProps = null;

    /**
     * @param string $namespace The namespace of the repository.
     *
     * @return void
     */
    public function setRepositoryNamespace(string $namespace) : void
    {
        $this->repositoryNamespace = $namespace;
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
     * @param array $props An associative array of property name to type.
     *
     * @return void
     */
    public function setIdentityProps(array $props) : void
    {
        $this->identityProps = $props;
    }

    /**
     * Builds and returns the repository source code.
     *
     * @return string
     *
     * @throws \RuntimeException If data are missing.
     * @throws \ReflectionException If a class does not exist.
     */
    public function build() : string
    {
        $checks = [
            $this->repositoryNamespace,
            $this->entityClassName,
            $this->identityProps
        ];

        foreach ($checks as $check) {
            if ($check === null) {
                throw new \RuntimeException('Missing data to build repository.');
            }
        }

        $imports = [
            $this->entityClassName
        ];

        $entityClassShortName = (new \ReflectionClass($this->entityClassName))->getShortName();

        $code = file_get_contents(__DIR__ . '/RepositoryTemplate.php');

        $code = str_replace('REPO_NAMESPACE', $this->repositoryNamespace, $code);
        $code = str_replace('CLASS_NAME', $entityClassShortName, $code);
        $code = str_replace('ENTITY_PROP_NAME', $this->getParamNameForClassName($entityClassShortName), $code);

        // Identity props & array

        $builtInTypes = [
            'bool',
            'int',
            'float',
            'string',
            'array',
            'object',
            'callable',
            'iterable'
        ];

        $identityProps = [];
        $identityArray = [];

        foreach ($this->identityProps as $prop => $type) {
            $typeLower = strtolower($type);

            if (in_array($typeLower, $builtInTypes, true)) {
                $type = $typeLower;
            } else {
                $imports[] = $type;
                $type = (new \ReflectionClass($type))->getShortName();
            }

            $identityProps[] = $type . ' $' . $prop;
            $identityArray[] = var_export($prop, true) . ' => $' . $prop;
        }

        $code = str_replace('$IDENTITY_PROPS', implode(', ', $identityProps), $code);
        $code = str_replace('IDENTITY_ARRAY', '[' . implode(', ', $identityArray) . ']', $code);

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

    /**
     * Returns a suitable parameter name for a class name.
     *
     * Examples: 'User' => 'user', 'ABBREntity' => 'abbrEntity'.
     *
     * @param string $className
     *
     * @return string
     */
    private function getParamNameForClassName(string $className) : string
    {
        $length = strlen($className);

        $upperLength = 0;

        for ($i = 0; $i < $length; $i++) {
            if ($this->isUppercase($className[$i])) {
                $upperLength++;
            } else {
                break;
            }
        }

        if ($upperLength === 0) {
            return $className;
        }

        if ($upperLength > 1) {
            $upperLength--;
        }

        return strtolower(substr($className, 0, $upperLength)) . substr($className, $upperLength);
    }

    /**
     * Checks if an ASCII letter is uppercase.
     *
     * @param string $letter
     *
     * @return bool
     */
    private function isUppercase(string $letter) : bool
    {
        $ord = ord($letter);

        return ($ord >= 65) && ($ord <= 90);
    }
}
