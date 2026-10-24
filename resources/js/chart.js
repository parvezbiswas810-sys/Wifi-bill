/**
 * Minimal dependency-free bar/line chart renderer.
 *
 * Keeps the bundle small and works without any external charting library,
 * which matters on shared hosting.
 */

const PALETTE = {
    line: '#0074cd',
    fillTop: 'rgba(12, 147, 240, 0.28)',
    fillBottom: 'rgba(12, 147, 240, 0.02)',
    grid: 'rgba(100, 116, 139, 0.18)',
    text: '#64748b',
};

function formatMoney(value) {
    return new Intl.NumberFormat(undefined, { maximumFractionDigits: 0 }).format(value);
}

function cssVar(name, fallback) {
    const v = getComputedStyle(document.documentElement).getPropertyValue(name);
    return v && v.trim() ? v.trim() : fallback;
}

/**
 * Draws a smooth area chart on a canvas.
 *
 * @param {HTMLCanvasElement} canvas
 * @param {{labels: string[], values: number[]}} data
 */
export function drawAreaChart(canvas, data) {
    if (!canvas || !data || !Array.isArray(data.labels) || data.labels.length === 0) {
        return;
    }

    const dpr = window.devicePixelRatio || 1;
    const cssWidth = canvas.parentElement.clientWidth || 600;
    const cssHeight = canvas.parentElement.clientHeight || 224;

    canvas.width = cssWidth * dpr;
    canvas.height = cssHeight * dpr;
    canvas.style.width = cssWidth + 'px';
    canvas.style.height = cssHeight + 'px';

    const ctx = canvas.getContext('2d');
    ctx.setTransform(dpr, 0, 0, dpr, 0, 0);
    ctx.clearRect(0, 0, cssWidth, cssHeight);

    const padTop = 14;
    const padBottom = 30;
    const padLeft = 8;
    const padRight = 8;
    const plotW = cssWidth - padLeft - padRight;
    const plotH = cssHeight - padTop - padBottom;

    const values = data.values.map((v) => Number(v) || 0);
    const maxValue = Math.max(...values, 1);
    const niceMax = maxValue * 1.15;

    const textColor = cssVar('--text-muted', PALETTE.text);
    const gridColor = PALETTE.grid;

    // Horizontal grid lines with value labels.
    ctx.font = '10px ' + cssVar('--font-sans', 'sans-serif');
    ctx.textAlign = 'right';
    ctx.textBaseline = 'middle';

    const gridLines = 4;
    for (let i = 0; i <= gridLines; i++) {
        const y = padTop + (plotH / gridLines) * i;
        ctx.strokeStyle = gridColor;
        ctx.lineWidth = 1;
        ctx.beginPath();
        ctx.moveTo(padLeft, y);
        ctx.lineTo(padLeft + plotW, y);
        ctx.stroke();

        const value = niceMax - (niceMax / gridLines) * i;
        ctx.fillStyle = textColor;
        ctx.fillText(formatMoney(value), padLeft + plotW - 2, y - 6);
    }

    const stepX = values.length > 1 ? plotW / (values.length - 1) : plotW;
    const points = values.map((v, i) => ({
        x: padLeft + stepX * i,
        y: padTop + plotH - (v / niceMax) * plotH,
    }));

    // Area fill.
    const gradient = ctx.createLinearGradient(0, padTop, 0, padTop + plotH);
    gradient.addColorStop(0, PALETTE.fillTop);
    gradient.addColorStop(1, PALETTE.fillBottom);

    ctx.beginPath();
    ctx.moveTo(points[0].x, padTop + plotH);
    points.forEach((p, i) => {
        if (i === 0) {
            ctx.lineTo(p.x, p.y);
            return;
        }
        const prev = points[i - 1];
        const cx = (prev.x + p.x) / 2;
        ctx.bezierCurveTo(cx, prev.y, cx, p.y, p.x, p.y);
    });
    ctx.lineTo(points[points.length - 1].x, padTop + plotH);
    ctx.closePath();
    ctx.fillStyle = gradient;
    ctx.fill();

    // Line stroke.
    ctx.beginPath();
    points.forEach((p, i) => {
        if (i === 0) {
            ctx.moveTo(p.x, p.y);
            return;
        }
        const prev = points[i - 1];
        const cx = (prev.x + p.x) / 2;
        ctx.bezierCurveTo(cx, prev.y, cx, p.y, p.x, p.y);
    });
    ctx.strokeStyle = PALETTE.line;
    ctx.lineWidth = 2.25;
    ctx.lineJoin = 'round';
    ctx.lineCap = 'round';
    ctx.stroke();

    // Data points.
    points.forEach((p) => {
        ctx.beginPath();
        ctx.arc(p.x, p.y, 2.6, 0, Math.PI * 2);
        ctx.fillStyle = '#ffffff';
        ctx.fill();
        ctx.strokeStyle = PALETTE.line;
        ctx.lineWidth = 1.8;
        ctx.stroke();
    });

    // X axis labels, thinned out so they never overlap.
    ctx.textAlign = 'center';
    ctx.textBaseline = 'top';
    ctx.fillStyle = textColor;

    const maxLabels = Math.max(2, Math.floor(cssWidth / 64));
    const labelStep = Math.ceil(data.labels.length / maxLabels);

    data.labels.forEach((label, i) => {
        if (i % labelStep !== 0 && i !== data.labels.length - 1) {
            return;
        }
        ctx.fillText(label, points[i].x, padTop + plotH + 8);
    });
}

/** Boots the dashboard chart and keeps it in sync with theme/tab changes. */
export function initCollectionChart() {
    const canvas = document.getElementById('collection-chart');
    const dataEl = document.getElementById('chart-data');
    if (!canvas || !dataEl) {
        return;
    }

    let payload;
    try {
        payload = JSON.parse(dataEl.textContent);
    } catch (e) {
        return;
    }

    let current = 'monthly';

    const render = () => {
        const series = payload[current];
        if (series) {
            drawAreaChart(canvas, series);
        }
    };

    window.addEventListener('chart-tab', (event) => {
        current = event.detail || 'monthly';
        render();
    });

    window.addEventListener('resize', () => {
        window.clearTimeout(canvas._resizeTimer);
        canvas._resizeTimer = window.setTimeout(render, 150);
    });

    render();
}
