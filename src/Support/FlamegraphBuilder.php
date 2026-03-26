<?php

namespace Exabyssus\LaravelProfiler\Support;

class FlamegraphBuilder
{
    public function build(string $collapsed): array
    {
        $collapsed = trim($collapsed);

        if ($collapsed === '') {
            return [];
        }

        $tree = ['name' => 'root', 'value' => 0, 'children' => []];

        foreach (preg_split('/\r\n|\r|\n/', $collapsed) ?: [] as $line) {
            $this->ingestLine($tree, trim($line));
        }

        if ($tree['value'] === 0) {
            return [];
        }

        $rows = [];
        $offset = 0;

        uasort($tree['children'], fn (array $left, array $right): int => $right['value'] <=> $left['value']);

        foreach ($tree['children'] as $child) {
            $this->appendNode($child, 0, $offset, $tree['value'], $rows);
            $offset += $child['value'];
        }

        ksort($rows);

        return array_values($rows);
    }

    public function buildTree(string $collapsed): array
    {
        $collapsed = trim($collapsed);

        if ($collapsed === '') {
            return [];
        }

        $tree = ['name' => 'root', 'value' => 0, 'children' => []];

        foreach (preg_split('/\r\n|\r|\n/', $collapsed) ?: [] as $line) {
            $this->ingestLine($tree, trim($line));
        }

        if ($tree['value'] === 0) {
            return [];
        }

        return $this->normalizeChildren($tree);
    }

    private function normalizeChildren(array $node): array
    {
        $children = array_values($node['children']);

        usort($children, fn (array $left, array $right): int => $right['value'] <=> $left['value']);

        return [
            'name' => $node['name'],
            'value' => $node['value'],
            'children' => array_map(fn (array $child): array => $this->normalizeChildren($child), $children),
        ];
    }

    private function ingestLine(array &$tree, string $line): void
    {
        if ($line === '') {
            return;
        }

        $separatorPosition = strrpos($line, ' ');

        if ($separatorPosition === false) {
            return;
        }

        $count = (int) substr($line, $separatorPosition + 1);
        $stack = explode(';', trim(substr($line, 0, $separatorPosition)));

        if ($count <= 0 || $stack === ['']) {
            return;
        }

        $tree['value'] += $count;
        $cursor = &$tree;

        foreach ($stack as $frame) {
            $frame = trim($frame);

            if ($frame === '') {
                continue;
            }

            if (! isset($cursor['children'][$frame])) {
                $cursor['children'][$frame] = ['name' => $frame, 'value' => 0, 'children' => []];
            }

            $cursor['children'][$frame]['value'] += $count;
            $cursor = &$cursor['children'][$frame];
        }
    }

    private function appendNode(array $node, int $depth, int $start, int $total, array &$rows): void
    {
        $rows[$depth][] = [
            'label' => $node['name'],
            'samples' => $node['value'],
            'left' => round(($start / $total) * 100, 4),
            'width' => round(($node['value'] / $total) * 100, 4),
            'color' => sprintf('hsl(%d 70%% 62%%)', abs(crc32($node['name'])) % 360),
        ];

        $childOffset = $start;
        uasort($node['children'], fn (array $left, array $right): int => $right['value'] <=> $left['value']);

        foreach ($node['children'] as $child) {
            $this->appendNode($child, $depth + 1, $childOffset, $total, $rows);
            $childOffset += $child['value'];
        }
    }
}