@script
    <script>
        const sanitizeFormulaTokens = (tokens) => {
            if (!Array.isArray(tokens)) {
                return [];
            }

            return tokens
                .filter((token) => token && typeof token === 'object' && token.type)
                .map((token) => {
                    if (token.type === 'fn') {
                        const args = Array.isArray(token.args) ? token.args : [];

                        return {
                            ...token,
                            args: [
                                sanitizeFormulaTokens(args[0]),
                                sanitizeFormulaTokens(args[1]),
                            ],
                        };
                    }

                    return token;
                });
        };

        Alpine.data('formulaBuilder', (initialTokens = []) => ({
            tokens: sanitizeFormulaTokens(initialTokens),
            literalValue: '',
            segments: [],
            gaps: [],
            cursorGapIndex: 0,

            init() {
                this.rebuildLayout();
                this.cursorGapIndex = Math.max(0, this.gaps.length - 1);
            },

            get preview() {
                return this.describeTokens(this.tokens).trim();
            },

            get previewText() {
                const text = this.preview;

                return text !== '' ? text : '— fórmula vacía —';
            },

            get hasPreview() {
                return this.previewText !== '— fórmula vacía —';
            },

            getArrayAtPath(path) {
                if (!Array.isArray(path) || path.length === 0) {
                    return this.tokens;
                }

                let cur = this.tokens;
                for (let p = 0; p < path.length; p += 2) {
                    const ti = path[p];
                    const ai = path[p + 1];
                    const fn = cur[ti];
                    if (!fn || fn.type !== 'fn') {
                        return this.tokens;
                    }
                    if (!Array.isArray(fn.args)) {
                        fn.args = [
                            [],
                            [],
                        ];
                    }
                    if (!Array.isArray(fn.args[ai])) {
                        fn.args[ai] = [];
                    }
                    cur = fn.args[ai];
                }

                return cur;
            },

            computeSegmentsAndGaps(tokensArr) {
                const gaps = [];
                const segments = [];
                const visit = (arr, path) => {
                    if (!Array.isArray(arr)) {
                        return;
                    }
                    for (let i = 0; i <= arr.length; i++) {
                        const gapIx = gaps.length;
                        gaps.push({
                            path: [...path],
                            insertIndex: i,
                        });
                        segments.push({
                            kind: 'gap',
                            gapIx,
                            path: [...path],
                            insertIndex: i,
                        });
                        if (i < arr.length) {
                            const t = arr[i];
                            if (!t || typeof t !== 'object' || !t.type) {
                                continue;
                            }
                            if (t.type === 'fn' && t.name === 'min') {
                                if (!Array.isArray(t.args)) {
                                    t.args = [
                                        [],
                                        [],
                                    ];
                                }
                                if (!Array.isArray(t.args[0])) {
                                    t.args[0] = [];
                                }
                                if (!Array.isArray(t.args[1])) {
                                    t.args[1] = [];
                                }
                                segments.push({
                                    kind: 'chrome',
                                    text: 'min(',
                                });
                                visit(t.args[0], path.concat(i, 0));
                                segments.push({
                                    kind: 'chrome',
                                    text: ',',
                                });
                                visit(t.args[1], path.concat(i, 1));
                                segments.push({
                                    kind: 'chrome',
                                    text: ')',
                                });
                            } else {
                                segments.push({
                                    kind: 'token',
                                    path: [...path],
                                    index: i,
                                    token: t,
                                });
                            }
                        }
                    }
                };
                visit(tokensArr, []);

                return {
                    gaps,
                    segments,
                };
            },

            rebuildLayout() {
                const {
                    gaps,
                    segments,
                } = this.computeSegmentsAndGaps(this.tokens);
                this.gaps = gaps;
                this.segments = segments;
                if (this.gaps.length === 0) {
                    this.gaps = [{
                        path: [],
                        insertIndex: 0,
                    }];
                    this.segments = [{
                        kind: 'gap',
                        gapIx: 0,
                        path: [],
                        insertIndex: 0,
                    }];
                }
                if (this.cursorGapIndex >= this.gaps.length) {
                    this.cursorGapIndex = Math.max(0, this.gaps.length - 1);
                }
            },

            findGapIndex(path, insertIndex) {
                return this.gaps.findIndex((g) => g.insertIndex === insertIndex &&
                    g.path.length === path.length &&
                    g.path.every((v, j) => v === path[j]));
            },

            get gapNavLabel() {
                const g = this.gaps[this.cursorGapIndex];
                if (!g || g.path.length === 0) {
                    return 'Expresión principal';
                }

                return 'Dentro de min()';
            },

            navCanLeft() {
                return this.cursorGapIndex > 0;
            },

            navCanRight() {
                return this.cursorGapIndex < this.gaps.length - 1;
            },

            navLeft() {
                if (this.navCanLeft()) {
                    this.cursorGapIndex--;
                }
            },

            navRight() {
                if (this.navCanRight()) {
                    this.cursorGapIndex++;
                }
            },

            describeTokens(tokens) {
                if (!Array.isArray(tokens)) {
                    return '';
                }

                return tokens
                    .map((token) => this.describeToken(token))
                    .join(' ');
            },

            describeToken(token) {
                if (!token || typeof token !== 'object' || !token.type) {
                    return '';
                }

                if (token.type === 'fn') {
                    const args = Array.isArray(token.args) ? token.args : [];
                    const left = Array.isArray(args[0]) ? this.describeTokens(args[0]) : '';
                    const right = Array.isArray(args[1]) ? this.describeTokens(args[1]) : '';

                    return `${token.name ?? 'min'}(${left}, ${right})`;
                }

                if (token.type === 'var') {
                    return token.key ?? '';
                }

                return token.value ?? '';
            },

            syncTokens() {
                this.tokens = [...this.tokens];
                this.rebuildLayout();
            },

            addMin() {
                const g = this.gaps[this.cursorGapIndex];
                const arr = this.getArrayAtPath(g.path);
                const fnTok = {
                    type: 'fn',
                    name: 'min',
                    args: [
                        [],
                        [],
                    ],
                };
                arr.splice(g.insertIndex, 0, fnTok);
                this.syncTokens();
                const innerPath = [...g.path, g.insertIndex, 0];
                const ix = this.findGapIndex(innerPath, 0);
                if (ix >= 0) {
                    this.cursorGapIndex = ix;
                }
            },

            addToken(type, value) {
                const g = this.gaps[this.cursorGapIndex];
                const arr = this.getArrayAtPath(g.path);
                const token = type === 'var' ? {
                    type: 'var',
                    key: value,
                } : {
                    type: type,
                    value: value,
                };
                arr.splice(g.insertIndex, 0, token);
                this.syncTokens();
                const ix = this.findGapIndex(g.path, g.insertIndex + 1);
                if (ix >= 0) {
                    this.cursorGapIndex = ix;
                }
            },

            addLiteral() {
                const val = this.literalValue.trim();
                if (val === '' || isNaN(parseFloat(val))) {
                    return;
                }

                const g = this.gaps[this.cursorGapIndex];
                const arr = this.getArrayAtPath(g.path);
                arr.splice(g.insertIndex, 0, {
                    type: 'lit',
                    value: val,
                });
                this.literalValue = '';
                this.syncTokens();
                const ix = this.findGapIndex(g.path, g.insertIndex + 1);
                if (ix >= 0) {
                    this.cursorGapIndex = ix;
                }
            },

            removeBeforeCursor() {
                const g = this.gaps[this.cursorGapIndex];
                const arr = this.getArrayAtPath(g.path);
                if (g.insertIndex <= 0) {
                    return;
                }

                arr.splice(g.insertIndex - 1, 1);
                this.syncTokens();
                const ix = this.findGapIndex(g.path, g.insertIndex - 1);
                if (ix >= 0) {
                    this.cursorGapIndex = ix;
                }
            },

            clearFormula() {
                this.tokens = [];
                this.cursorGapIndex = 0;
                this.syncTokens();
            },
        }));
    </script>
@endscript
