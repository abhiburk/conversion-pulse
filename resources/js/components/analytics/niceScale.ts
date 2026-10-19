/**
 * Round an axis up to clean tick values (0, 5, 10, 15… or 0, 250, 500…), always using whole numbers for counts.
 */
export function niceScale(
    maxValue: number,
    tickCount = 4,
): { max: number; ticks: number[] } {
    if (maxValue <= 0) {
        return { max: tickCount, ticks: steps(tickCount, 1) };
    }

    const rawStep = maxValue / tickCount;
    const magnitude = 10 ** Math.floor(Math.log10(rawStep));
    const niceStep =
        [1, 2, 2.5, 5, 10]
            .map((multiplier) => multiplier * magnitude)
            .find((candidate) => candidate >= rawStep) ?? 10 * magnitude;
    const step = Math.max(1, Math.ceil(niceStep));
    const count = Math.ceil(maxValue / step);

    return { max: count * step, ticks: steps(count, step) };
}

function steps(count: number, step: number): number[] {
    return Array.from({ length: count + 1 }, (_, index) => index * step);
}
