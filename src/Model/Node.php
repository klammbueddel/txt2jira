<?php

namespace App\Model;

class Node
{

    /**
     * @var Node[]
     */
    public $children = [];
    public ?Node $parent = null;

    public function addChild(Node $child): Node
    {
        $this->children[] = $child;
        $child->parent = $this;

        return $this;
    }

    public function prependChild(Node $child): Node
    {
        array_unshift($this->children, $child);
        $child->parent = $this;

        return $this;
    }

    public function __toString(): string
    {
        $buffer = "";

        foreach ($this->children as $child) {
            $buffer .= $child->__toString();
        }

        return $buffer;
    }

    public function indexOf(Node $node): int
    {
        return array_search($node, $this->children, true);
    }

    public function insertAt(Node $node, int $index): Node
    {
        array_splice($this->children, $index, 0, [$node]);

        $node->parent = $this;

        return $this;
    }

    public function delete(): Node
    {
        if ($this->parent) {
            $index = $this->parent->indexOf($this);
            array_splice($this->parent->children, $index, 1);
        }

        return $this;
    }

    public function insertSibling(Node $node): Node
    {
        $this->parent?->insertAt($node, $this->parent->indexOf($this) + 1);

        return $this;
    }

    public function getOneByCriteria(callable $criteria, $deep = false, $reverse = false, $offset = 0): ?Node
    {
        $children = $reverse ? array_reverse($this->children) : $this->children;

        foreach ($children as $child) {

            if ($reverse && $deep) {
                $result = $child->getOneByCriteria($criteria, $deep, $reverse, $offset);
                if ($result) {
                    return $result;
                }
            }

            if ($criteria($child)) {
                if ($offset > 0) {
                    echo "SKIP " . $child;
                    $offset--;
                } else {
                    return $child;
                }
            }

            if ($deep) {
                $result = $child->getOneByCriteria($criteria, $deep, $reverse, $offset);
                if ($result) {
                    return $result;
                }
            }
        }

        return null;
    }

    public function getByCriteria(callable $criteria, $deep = true, $reverse = false ): array
    {
        $result = [];

        $children = $reverse ? array_reverse($this->children) : $this->children;

        foreach ($children as $child) {
            if ($criteria($child)) {
                $result[] = $child;
            }

            if ($deep) {
                $result = array_merge($result, $child->getByCriteria($criteria, $deep, $reverse));
            }
        }

        return $result;
    }

    public function getByType(string $type, $deep = true, $reverse = false): array
    {
        return $this->getByCriteria(fn($node) => $node instanceof $type, $deep, $reverse);
    }

    public function getOneByType(string $type, $deep = false, $reverse = false, $offset = 0): ?Node
    {
        return $this->getOneByCriteria(fn($node) => $node instanceof $type, $deep, $reverse, $offset);
    }

    public function getIssues($deep = true, $reverse = false): array
    {
        return $this->getByCriteria(fn($node) => $node instanceof Issue, $deep, $reverse);
    }

    public function getIssue($deep = true, $reverse = false, $offset = 0): ?Issue
    {
        return $this->getOneByCriteria(fn($node) => $node instanceof Issue, $deep, $reverse, $offset);
    }

    public function getSibling(int $offset = 1): ?Node
    {
        if ($this->parent) {
            $index = $this->parent->indexOf($this);
            $index += $offset;
            if (isset($this->parent->children[$index])) {
                return $this->parent->children[$index];
            }
        }

        return null;
    }

}
