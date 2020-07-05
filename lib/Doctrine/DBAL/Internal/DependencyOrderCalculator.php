<?php

namespace Doctrine\DBAL\Internal;

use function array_reverse;

/**
 * DependencyOrderCalculator implements topological sorting, which is an ordering
 * algorithm for directed graphs (DG) and/or directed acyclic graphs (DAG) by
 * using a depth-first searching (DFS) to traverse the graph built in memory.
 * This algorithm have a linear running time based on nodes (V) and dependency
 * between the nodes (E), resulting in a computational complexity of O(V + E).
 */
final class DependencyOrderCalculator
{
    public const NOT_VISITED = 0;
    public const IN_PROGRESS = 1;
    public const VISITED     = 2;

    /**
     * Matrix of nodes (aka. vertex).
     * Keys are provided hashes and values are the node definition objects.
     *
     * @var array<string,DependencyOrderNode>
     */
    private $nodeList = [];

    /**
     * Volatile variable holding calculated nodes during sorting process.
     *
     * @var array<object>
     */
    private $sortedNodeList = [];

    /**
     * Checks for node (vertex) existence in graph.
     */
    public function hasNode(string $hash): bool
    {
        return isset($this->nodeList[$hash]);
    }

    /**
     * Adds a new node (vertex) to the graph, assigning its hash and value.
     *
     * @param object $node
     */
    public function addNode(string $hash, $node): void
    {
        $vertex = new DependencyOrderNode();

        $vertex->hash  = $hash;
        $vertex->state = self::NOT_VISITED;
        $vertex->value = $node;

        $this->nodeList[$hash] = $vertex;
    }

    /**
     * Adds a new dependency (edge) to the graph using their hashes.
     */
    public function addDependency(string $fromHash, string $toHash): void
    {
        $vertex = $this->nodeList[$fromHash];
        $edge   = new DependencyOrderEdge();

        $edge->from = $fromHash;
        $edge->to   = $toHash;

        $vertex->dependencyList[$toHash] = $edge;
    }

    /**
     * Return a valid order list of all current nodes.
     * The desired topological sorting is the reverse post order of these searches.
     *
     * {@internal Highly performance-sensitive method.}
     *
     * @return array<object>
     */
    public function sort(): array
    {
        foreach ($this->nodeList as $vertex) {
            if ($vertex->state !== self::NOT_VISITED) {
                continue;
            }

            $this->visit($vertex);
        }

        $sortedList = $this->sortedNodeList;

        $this->nodeList       = [];
        $this->sortedNodeList = [];

        return array_reverse($sortedList);
    }

    /**
     * Visit a given node definition for reordering.
     *
     * {@internal Highly performance-sensitive method.}
     */
    private function visit(DependencyOrderNode $vertex): void
    {
        $vertex->state = self::IN_PROGRESS;

        foreach ($vertex->dependencyList as $edge) {
            $adjacentVertex = $this->nodeList[$edge->to];

            switch ($adjacentVertex->state) {
                case self::VISITED:
                case self::IN_PROGRESS:
                    // Do nothing, since node was already visited or is
                    // currently visited
                    break;

                case self::NOT_VISITED:
                    $this->visit($adjacentVertex);
            }
        }

        if ($vertex->state === self::VISITED) {
            return;
        }

        $vertex->state = self::VISITED;

        $this->sortedNodeList[] = $vertex->value;
    }
}
