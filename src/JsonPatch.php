<?php

namespace Odanijel\JsonDiff;

use Odanijel\JsonDiff\JsonPatch\Add;
use Odanijel\JsonDiff\JsonPatch\Copy;
use Odanijel\JsonDiff\JsonPatch\Move;
use Odanijel\JsonDiff\JsonPatch\OpPath;
use Odanijel\JsonDiff\JsonPatch\OpPathFrom;
use Odanijel\JsonDiff\JsonPatch\OpPathValue;
use Odanijel\JsonDiff\JsonPatch\Remove;
use Odanijel\JsonDiff\JsonPatch\Replace;
use Odanijel\JsonDiff\JsonPatch\Test;

/**
 * JSON Patch is specified in [RFC 6902](http://tools.ietf.org/html/rfc6902) from the IETF.
 *
 * Class JsonPatch
 */
class JsonPatch implements \JsonSerializable
{
    /**
     * Disallow converting empty array to object for key creation
     * @see JsonPointer::STRICT_MODE
     */
    const STRICT_MODE = 2;

    private $flags = 0;

    /**
     * @param int $options
     * @return $this
     */
    public function setFlags($options)
    {
        $this->flags = $options;
        return $this;
    }

    /** @var OpPath[] */
    private $operations = array();

    /**
     * @param array $data
     * @return JsonPatch
     * @throws Exception
     */
    public static function import(array $data)
    {
        $result = new JsonPatch();
        foreach ($data as $operation) {
            /** @var OpPath|OpPathValue|OpPathFrom $operation */
            if (is_array($operation)) {
                $operation = (object)$operation;
            }

            if (!isset($operation->op)) {
                throw new Exception('Missing "op" in operation data');
            }
            if (!isset($operation->path)) {
                throw new Exception('Missing "path" in operation data');
            }

            $op = null;
            switch ($operation->op) {
                case Add::OP:
                    $op = new Add();
                    break;
                case Copy::OP:
                    $op = new Copy();
                    break;
                case Move::OP:
                    $op = new Move();
                    break;
                case Remove::OP:
                    $op = new Remove();
                    break;
                case Replace::OP:
                    $op = new Replace();
                    break;
                case Test::OP:
                    $op = new Test();
                    break;
                default:
                    throw new Exception('Unknown "op": ' . $operation->op);
            }
            $op->path = $operation->path;
            if ($op instanceof OpPathValue) {
                if (!array_key_exists('value', (array)$operation)) {
                    throw new Exception('Missing "value" in operation data');
                }
                $op->value = $operation->value;
            } elseif ($op instanceof OpPathFrom) {
                if (!isset($operation->from)) {
                    throw new Exception('Missing "from" in operation data');
                }
                $op->from = $operation->from;
            }
            $result->operations[] = $op;
        }
        return $result;
    }

    public static function export(JsonPatch $patch)
    {
        $result = array();
        foreach ($patch->operations as $operation) {
            $result[] = (object)(array)$operation;
        }

        return $result;
    }

    public function op(OpPath $op)
    {
        $this->operations[] = $op;
        return $this;
    }

    public function jsonSerialize()
    {
        return self::export($this);
    }

    /**
     * @param mixed $original
     * @param bool $stopOnError
     * @return Exception[] array of errors
     * @throws Exception
     */
    public function apply(&$original, $stopOnError = true)
    {
        $errors = array();
        foreach ($this->operations as $operation) {
            try {
                $pathItems = JsonPointer::splitPath($operation->path);
                switch (true) {
                    case $operation instanceof Add:
                        JsonPointer::add($original, $pathItems, $operation->value, $this->flags);
                        break;
                    case $operation instanceof Copy:
                        $fromItems = JsonPointer::splitPath($operation->from);
                        $value = JsonPointer::get($original, $fromItems);
                        JsonPointer::add($original, $pathItems, $value, $this->flags);
                        break;
                    case $operation instanceof Move:
                        $fromItems = JsonPointer::splitPath($operation->from);
                        $value = JsonPointer::get($original, $fromItems);
                        JsonPointer::remove($original, $fromItems);
                        JsonPointer::add($original, $pathItems, $value, $this->flags);
                        break;
                    case $operation instanceof Remove:
                        JsonPointer::remove($original, $pathItems);
                        break;
                    case $operation instanceof Replace:
                        JsonPointer::get($original, $pathItems);
                        JsonPointer::remove($original, $pathItems);
                        JsonPointer::add($original, $pathItems, $operation->value, $this->flags);
                        break;
                    case $operation instanceof Test:
                        $value = JsonPointer::get($original, $pathItems);
                        $diff = new JsonDiff($operation->value, $value,
                            JsonDiff::STOP_ON_DIFF);
                        if ($diff->getDiffCnt() !== 0) {
                            throw new Exception('Test operation ' . json_encode($operation, JSON_UNESCAPED_SLASHES)
                                . ' failed: ' . json_encode($value));
                        }
                        break;
                }
            } catch (Exception $exception) {
                if ($stopOnError) {
                    throw $exception;
                } else {
                    $errors[] = $exception;
                }
            }
        }
        return $errors;
    }
}
