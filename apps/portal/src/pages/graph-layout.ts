import type { GraphEdge, GraphNode } from '@/data/types';

export type GraphPoint = { id: string; x: number; y: number; radius: number };

const random = (seed: string) => {
  let value = [...seed].reduce(
    (result, letter) => (result * 31 + letter.charCodeAt(0)) >>> 0,
    2166136261,
  );
  return () => {
    value += 0x6d2b79f5;
    let output = value;
    output = Math.imul(output ^ (output >>> 15), output | 1);
    output ^= output + Math.imul(output ^ (output >>> 7), output | 61);
    return ((output ^ (output >>> 14)) >>> 0) / 4294967296;
  };
};

/** Layout determinístico: a API fornece relações, e o Portal mantém a apresentação. */
export function forceLayout(
  nodes: readonly GraphNode[],
  edges: readonly GraphEdge[],
  seed: string,
): GraphPoint[] {
  const next = random(seed);
  const points = nodes.map((node) => ({
    id: node.id,
    x: 100 + next() * 520,
    y: 70 + next() * 330,
    radius: Math.min(15, Math.max(7, 6 + Math.sqrt(Math.max(1, node.weight)) * 3)),
  }));
  const index = new Map(points.map((point) => [point.id, point]));
  for (let iteration = 0; iteration < 120; iteration += 1) {
    for (let left = 0; left < points.length; left += 1)
      for (let right = left + 1; right < points.length; right += 1) {
        const first = points[left];
        const second = points[right];
        if (!first || !second) continue;
        const dx = second.x - first.x;
        const dy = second.y - first.y;
        const distance = Math.max(20, Math.hypot(dx, dy));
        const force = 800 / (distance * distance);
        first.x -= (dx / distance) * force;
        first.y -= (dy / distance) * force;
        second.x += (dx / distance) * force;
        second.y += (dy / distance) * force;
      }
    edges.forEach((edge) => {
      const first = index.get(edge.origin);
      const second = index.get(edge.destination);
      if (!first || !second) return;
      const dx = second.x - first.x;
      const dy = second.y - first.y;
      const distance = Math.max(1, Math.hypot(dx, dy));
      const force = (distance - 125) * 0.015;
      first.x += (dx / distance) * force;
      first.y += (dy / distance) * force;
      second.x -= (dx / distance) * force;
      second.y -= (dy / distance) * force;
    });
    points.forEach((point) => {
      point.x = Math.max(36, Math.min(675, point.x));
      point.y = Math.max(28, Math.min(440, point.y));
    });
  }
  return points;
}
