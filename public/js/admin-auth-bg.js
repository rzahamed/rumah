(function () {
    'use strict';

    var VERTEX_SHADER = [
        'attribute vec2 a_position;',
        'void main() {',
        '    gl_Position = vec4(a_position, 0.0, 1.0);',
        '}'
    ].join('\n');

    var FRAGMENT_SHADER = [
        'precision highp float;',
        '',
        'uniform float u_time;',
        'uniform vec2 u_resolution;',
        'uniform vec2 u_mouse;',
        '',
        'float wave(vec2 p, float freq, float speed, float phase) {',
        '    return sin(p.x * freq + u_time * speed + phase)',
        '         * cos(p.y * freq * 0.7 + u_time * speed * 0.8 + phase * 1.3);',
        '}',
        '',
        'vec2 warp(vec2 p, float t) {',
        '    float wx = sin(p.y * 2.0 + t * 0.12) * 0.3',
        '             + sin(p.y * 1.1 - t * 0.08 + 3.0) * 0.2',
        '             + sin(p.x * 1.5 + t * 0.1) * 0.15;',
        '    float wy = cos(p.x * 1.8 + t * 0.1 + 1.0) * 0.25',
        '             + cos(p.x * 0.9 - t * 0.07 + 5.0) * 0.2',
        '             + cos(p.y * 1.3 + t * 0.09) * 0.15;',
        '    return vec2(wx, wy);',
        '}',
        '',
        'void main() {',
        '    vec2 uv = gl_FragCoord.xy / u_resolution.xy;',
        '    float aspect = u_resolution.x / u_resolution.y;',
        '    vec2 p = (uv - 0.5) * vec2(aspect, 1.0);',
        '',
        '    float t = u_time * 0.4;',
        '',
        '    vec2 mouse = (u_mouse - 0.5) * vec2(aspect, 1.0);',
        '    float mDist = length(p - mouse);',
        '    vec2 mPush = (p - mouse) * smoothstep(0.6, 0.0, mDist) * 0.03;',
        '',
        '    vec2 w1 = warp(p + mPush, t);',
        '    vec2 w2 = warp(p + w1 * 0.5, t * 1.1 + 10.0);',
        '    vec2 warped = p + w1 * 0.4 + w2 * 0.2;',
        '',
        '    float aberration = 0.025;',
        '    vec2 dir = normalize(warped + vec2(0.001));',
        '    vec2 offsetR = warped + dir * aberration;',
        '    vec2 offsetG = warped;',
        '    vec2 offsetB = warped - dir * aberration;',
        '',
        '    float r_val = 0.0;',
        '    r_val += wave(offsetR * 2.0, 3.0, 0.15, 0.0) * 0.3;',
        '    r_val += wave(offsetR * 1.5, 2.0, 0.12, 2.0) * 0.25;',
        '    r_val += wave(offsetR * 3.0, 1.5, 0.1, 4.0) * 0.15;',
        '',
        '    float g_val = 0.0;',
        '    g_val += wave(offsetG * 2.0, 3.0, 0.15, 0.3) * 0.3;',
        '    g_val += wave(offsetG * 1.5, 2.0, 0.12, 2.3) * 0.25;',
        '    g_val += wave(offsetG * 3.0, 1.5, 0.1, 4.3) * 0.15;',
        '',
        '    float b_val = 0.0;',
        '    b_val += wave(offsetB * 2.0, 3.0, 0.15, 0.6) * 0.3;',
        '    b_val += wave(offsetB * 1.5, 2.0, 0.12, 2.6) * 0.25;',
        '    b_val += wave(offsetB * 3.0, 1.5, 0.1, 4.6) * 0.15;',
        '',
        '    float r2 = sin(warped.x * 1.2 + warped.y * 0.8 + t * 0.08) * 0.2;',
        '    float g2 = sin(warped.x * 1.2 + warped.y * 0.8 + t * 0.08 + 0.4) * 0.2;',
        '    float b2 = sin(warped.x * 1.2 + warped.y * 0.8 + t * 0.08 + 0.8) * 0.2;',
        '',
        '    vec3 gradient = vec3(',
        '        r_val + r2,',
        '        g_val + g2,',
        '        b_val + b2',
        '    );',
        '',
        '    float streak1 = smoothstep(0.15, 0.0, abs(wave(warped * 1.8, 2.5, 0.13, 1.0)));',
        '    float streak2 = smoothstep(0.2, 0.0, abs(wave(warped * 1.3, 2.0, 0.1, 3.5)));',
        '    float streak3 = smoothstep(0.25, 0.0, abs(wave(warped * 2.2, 1.8, 0.11, 6.0)));',
        '    float streaks = streak1 * 0.5 + streak2 * 0.35 + streak3 * 0.25;',
        '    streaks = smoothstep(0.0, 0.8, streaks);',
        '',
        '    vec3 base = vec3(0.97, 0.97, 0.98);',
        '',
        '    vec3 iridescent = vec3(',
        '        0.5 + gradient.r * 0.5,',
        '        0.5 + gradient.g * 0.5,',
        '        0.5 + gradient.b * 0.5',
        '    );',
        '',
        '    vec3 streakColor = mix(vec3(0.85), iridescent, 0.7);',
        '',
        '    float silver = (streaks * 0.3) * (1.0 - length(gradient) * 0.5);',
        '    vec3 silverTint = vec3(0.88, 0.88, 0.90);',
        '',
        '    vec3 color = base;',
        '    color = mix(color, silverTint, silver);',
        '    color = mix(color, streakColor, streaks * 0.65);',
        '',
        '    float highlight = streak1 * streak2 * 3.0;',
        '    highlight = smoothstep(0.0, 1.0, highlight);',
        '    color = mix(color, vec3(1.0), highlight * 0.15);',
        '',
        '    vec2 vigUv = uv - 0.5;',
        '    float vig = 1.0 - dot(vigUv, vigUv) * 0.3;',
        '    color *= mix(0.95, 1.0, vig);',
        '',
        '    color = clamp(color, 0.0, 1.0);',
        '',
        '    gl_FragColor = vec4(color, 1.0);',
        '}'
    ].join('\n');

    var canvas = document.getElementById('admin-auth-bg-canvas');
    if (!canvas) return;

    var gl = canvas.getContext('webgl', {
        alpha: false,
        antialias: false,
        depth: false,
        stencil: false,
        preserveDrawingBuffer: false,
    });
    if (!gl) {
        // No WebGL: hide the canvas so the static CSS gradient shows.
        canvas.style.display = 'none';
        return;
    }

    function compileShader(type, source) {
        var shader = gl.createShader(type);
        gl.shaderSource(shader, source);
        gl.compileShader(shader);
        if (!gl.getShaderParameter(shader, gl.COMPILE_STATUS)) {
            gl.deleteShader(shader);
            return null;
        }
        return shader;
    }

    var vs = compileShader(gl.VERTEX_SHADER, VERTEX_SHADER);
    var fs = compileShader(gl.FRAGMENT_SHADER, FRAGMENT_SHADER);
    if (!vs || !fs) {
        canvas.style.display = 'none';
        return;
    }

    var program = gl.createProgram();
    gl.attachShader(program, vs);
    gl.attachShader(program, fs);
    gl.linkProgram(program);
    if (!gl.getProgramParameter(program, gl.LINK_STATUS)) {
        canvas.style.display = 'none';
        return;
    }
    gl.useProgram(program);

    var buffer = gl.createBuffer();
    gl.bindBuffer(gl.ARRAY_BUFFER, buffer);
    gl.bufferData(gl.ARRAY_BUFFER, new Float32Array([
        -1, -1, 1, -1, -1, 1,
        -1, 1, 1, -1, 1, 1,
    ]), gl.STATIC_DRAW);

    var aPos = gl.getAttribLocation(program, 'a_position');
    gl.enableVertexAttribArray(aPos);
    gl.vertexAttribPointer(aPos, 2, gl.FLOAT, false, 0, 0);

    var uTime = gl.getUniformLocation(program, 'u_time');
    var uResolution = gl.getUniformLocation(program, 'u_resolution');
    var uMouse = gl.getUniformLocation(program, 'u_mouse');

    var mouseX = 0.5, mouseY = 0.5;
    var startTime = Date.now();
    var animId = null;
    var reducedMotion = window.matchMedia('(prefers-reduced-motion: reduce)');

    function resize() {
        var dpr = Math.min(window.devicePixelRatio, 1.5);
        var w = Math.floor(canvas.clientWidth * dpr);
        var h = Math.floor(canvas.clientHeight * dpr);
        if (canvas.width !== w || canvas.height !== h) {
            canvas.width = w;
            canvas.height = h;
            gl.viewport(0, 0, w, h);
        }
    }

    var isVisible = true;

    function render() {
        if (!isVisible) {
            animId = requestAnimationFrame(render);
            return;
        }
        resize();
        var elapsed = (Date.now() - startTime) / 1000;
        gl.uniform1f(uTime, elapsed);
        gl.uniform2f(uResolution, canvas.width, canvas.height);
        gl.uniform2f(uMouse, mouseX, mouseY);
        gl.drawArrays(gl.TRIANGLES, 0, 6);
        animId = requestAnimationFrame(render);
    }

    document.addEventListener('visibilitychange', function () {
        isVisible = !document.hidden;
    });

    window.addEventListener('mousemove', function (e) {
        if (reducedMotion.matches) return;
        mouseX = e.clientX / window.innerWidth;
        mouseY = 1.0 - e.clientY / window.innerHeight;
    }, { passive: true });

    function renderStaticFrame() {
        // prefers-reduced-motion: one frozen frame of the same visual — a
        // static equivalent with no animation loop and no cursor effect.
        resize();
        gl.uniform1f(uTime, 12.0);
        gl.uniform2f(uResolution, canvas.width, canvas.height);
        gl.uniform2f(uMouse, 0.5, 0.5);
        gl.drawArrays(gl.TRIANGLES, 0, 6);
    }

    function applyMotionPreference() {
        if (reducedMotion.matches) {
            if (animId !== null) {
                cancelAnimationFrame(animId);
                animId = null;
            }
            renderStaticFrame();
        } else if (animId === null) {
            startTime = Date.now();
            render();
        }
    }

    window.addEventListener('resize', function () {
        if (reducedMotion.matches) {
            renderStaticFrame();
        }
    }, { passive: true });

    if (typeof reducedMotion.addEventListener === 'function') {
        reducedMotion.addEventListener('change', applyMotionPreference);
    }

    resize();
    applyMotionPreference();
})();
