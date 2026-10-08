/* Standalone design study. No API requests and no real rooms or saved contents. */
const { catalog, parts, messages } = window.PLUMMO_MOCK_DATA;
const editorCaps = {};
const readIcon = `<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M7 17 17 7M7 7h10v10"/></svg>`;
const backIcon = `<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="m12 5-7 7 7 7M5 12h14"/></svg>`;
const root = document.querySelector('#app');
const scenes = {
    tv: [
        'join',
        'lobby',
        'quiz',
        'quizReveal',
        'blind',
        'blindReveal',
        'drawing',
        'artistMissing',
        'presentation',
        'vote',
        'phraseReveal',
        'results',
        'ranking',
        'pause',
        'resume',
        'goal',
        'closed',
    ],
    phone: [
        'code',
        'codeError',
        'full',
        'identity',
        'lobby',
        'chooseGame',
        'packs',
        'settings',
        'target',
        'recap',
        'quiz',
        'sent',
        'quizReveal',
        'blind',
        'word',
        'drawing',
        'guess',
        'artistMissing',
        'found',
        'write',
        'presentation',
        'vote',
        'phraseReveal',
        'results',
        'ranking',
        'pause',
        'resume',
        'waiting',
        'connection',
        'goal',
        'reset',
        'closed',
    ],
    admin: [
        'login',
        'catalog',
        'content',
        'choices',
        'tags',
        'preview',
        'upload',
        'files',
        'importPreview',
        'importReport',
    ],
};
const titles = {
    code: 'join',
    codeError: 'codeError',
    identity: 'identity',
    chooseGame: 'chooseGame',
    quizReveal: 'correct',
    blindReveal: 'correct',
    word: 'pickWord',
    phraseReveal: 'results',
    guess: 'guess',
    reset: 'resetConfirm',
    content: 'content',
    choices: 'choices',
    preview: 'validation',
};
const state = {
    surface: innerWidth <= 760 ? 'phone' : 'tv',
    scene: 'quiz',
    locale: 'fr',
    stress: false,
    page: 0,
    menu: false,
    menuPage: 0,
    color: 0,
    accessory: 0,
    game: 'quiz',
    packs: [0],
    answer: 0,
    note: '',
    draft: {},
    editPage: 0,
    adminType: 'quiz',
    tag: 0,
    nickname: 'Léa',
    reader: null,
    readerPage: 0,
    strokes: [],
};
const names = ['Léa', 'Tom', 'Nora', 'Sam', 'Zoé', 'Max', 'Lou', 'Alex'];
const accessories = [
    'flower',
    'cap',
    'round-glasses',
    'crown',
    'bow-tie',
    'beanie',
    'sunglasses',
    'paintbrush',
];
const esc = (value) =>
    String(value).replace(
        /[&<>"']/g,
        (c) =>
            ({
                '&': '&amp;',
                '<': '&lt;',
                '>': '&gt;',
                '"': '&quot;',
                "'": '&#39;',
            })[c],
    );
const t = (key, vars = {}) => {
    let text = messages[state.locale][key] ?? key;
    if (typeof text !== 'string') return text;
    for (const [name, value] of Object.entries(vars))
        text = text.replaceAll(`{${name}}`, value);
    return text;
};
const button = (label, action, extra = '', classes = '') =>
    `<button class="action ${classes}" data-action="${action}" ${extra}>${esc(label)}</button>`;
const go = (scene, label, classes = '') =>
    button(t(label), `go:${scene}`, '', classes);
const icon = (kind) => {
    const paths = {
        quiz: '<path d="M9 9a3 3 0 1 1 5 2c-2 1-2 2-2 3"/><path d="M12 18h.01"/><rect x="3" y="3" width="18" height="18" rx="5"/>',
        blind: '<path d="M9 18V5l12-2v13M9 8l12-2"/><circle cx="6" cy="18" r="3"/><circle cx="18" cy="16" r="3"/>',
        drawing: '<path d="m4 20 4-1L21 6l-3-3L5 16zM14 7l3 3"/>',
        phrase: '<path d="M21 11a8 8 0 0 1-8 8H5l-3 3V5a2 2 0 0 1 2-2h9a8 8 0 0 1 8 8Z"/><path d="M7 8h8M7 12h5"/>',
    };
    return `<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">${paths[kind] ?? paths.quiz}</svg>`;
};
let svgId = 0;
function mascot(colorIndex = 0, accessoryId = 'flower') {
    const color = catalog.colors[colorIndex % catalog.colors.length];
    const item = catalog.accessories.find((x) => x.id === accessoryId);
    let base = parts.base;
    if (item?.coversPlumes)
        base = base.replace(/<g id="plummo-plumes">[\s\S]*?<\/g>/, '');
    base = base
        .replaceAll('#b99aef', color.light)
        .replaceAll('#9672dc', color.color)
        .replaceAll('#6950ac', color.dark);
    let body =
        (parts[`${accessoryId}-back`] ?? '') +
        base +
        (parts[accessoryId] ?? '');
    const prefix = `m${svgId++}-`;
    body = body
        .replace(/id="([^"]+)"/g, (_, id) => `id="${prefix}${id}"`)
        .replace(/url\(#([^)]*)\)/g, (_, id) => `url(#${prefix}${id})`);
    return `<svg viewBox="0 0 512 512" aria-hidden="true">${body}</svg>`;
}
function cast() {
    const tv = state.surface === 'tv';
    const count = tv ? 8 : 1;
    return `<div class="cast ${tv ? '' : 'solo'}" aria-label="${esc(t('legend'))}">${Array.from({ length: count }, (_, i) => `<div class="character">${state.note && i === count - 1 ? `<div class="bubble">${esc(state.note)}</div>` : ''}<span class="nickname">${esc(tv ? names[i] : state.nickname)}</span><span class="score">${tv ? 825 - i * 65 : 825}</span>${mascot(tv ? i : state.color, tv ? accessories[i] : accessories[state.accessory])}</div>`).join('')}</div>`;
}
function title(key, timer = false) {
    return `<div class="titlebar"><h1>${esc(t(key))}</h1>${timer ? '<div class="timer" aria-label="20 s">20</div>' : ''}</div>`;
}
const sub = (key, optional = false) =>
    `<p class="sub ${optional ? 'optional' : ''}">${esc(t(key))}</p>`;
const progress = (key = 'countAnswer') =>
    `<div class="progress"><span>${esc(t('round', { n: 2 }))}</span><div class="track" aria-hidden="true"></div><span>${esc(t(key))}</span></div>`;
function longText(text, length) {
    return (text + ' ')
        .repeat(Math.ceil(length / (text.length + 1)))
        .slice(0, length);
}
function question() {
    return state.stress ? longText(t('questionLong'), 500) : t('quizQuestion');
}
function questionBlock() {
    if (!state.stress) return title('quizQuestion', true);
    return `<div class="titlebar"><h1 class="summary">${esc(question())}</h1><div class="timer">20</div></div>${button(t('read'), 'readQuestion', '', 'secondary')}`;
}
function answerGrid(entries, type = 'quiz') {
    return `<div class="answers ${type === 'blind' ? 'blind' : type === 'phrase' ? 'phrases' : ''}">${entries
        .map((item, i) => {
            const index = item.index ?? i;
            const letter = String.fromCharCode(65 + index);
            const own =
                type === 'phrase' && state.surface === 'phone' && index === 0;
            return `<div class="answer-wrap"><button class="answer" data-action="${state.surface === 'tv' ? `readAnswer:${type}:${index}` : type === 'phrase' ? `vote:${index}` : `answer:${index}`}" ${own ? 'disabled' : ''}><span class="letter">${letter}</span><span class="answer-copy"><span class="summary">${esc(item.text)}</span>${item.secondary ? `<small class="summary">${esc(item.secondary)}</small>` : ''}${own ? `<small>${esc(t('own'))}</small>` : ''}</span></button><button class="read-small" data-action="readAnswer:${type}:${index}" aria-label="${esc(t('read'))} · ${letter}">${readIcon}</button></div>`;
        })
        .join('')}</div>`;
}
function quizEntries() {
    return t('answers').map((text, i) => ({
        text: state.stress ? longText(t('answerLong'), 200) : text,
        index: i,
    }));
}
function blindEntries() {
    return t('songs').map((text, i) => ({
        text: state.stress ? longText(text, 200) : text,
        secondary: state.stress
            ? longText(t('artists')[i], 200)
            : t('artists')[i],
        index: i,
    }));
}
function phraseEntries() {
    return t('phraseEntries').map((text, i) => ({
        text: state.stress
            ? longText(t('prefix'), 240) + ' ' + longText(text, 150)
            : t('prefix') + ' ' + text,
        index: i,
    }));
}
function pageSize(kind) {
    if (kind === 'catalog')
        return innerHeight <= 450
            ? 1
            : innerHeight <= 650
              ? 2
              : innerHeight <= 800
                ? 3
                : 4;
    if (innerHeight <= 450) return kind === 'phrase' ? 2 : 3;
    if (kind === 'phrase')
        return state.surface === 'tv' && innerWidth > 760 && innerHeight > 650
            ? 8
            : 4;
    return innerHeight <= 650 ? 4 : innerWidth <= 760 ? 5 : 6;
}
function pager(total, action = 'page') {
    const current = action === 'menuPage' ? state.menuPage : state.page;
    return `<div class="pager">${button(t('previous'), `${action}:-1`, current <= 0 ? 'disabled' : '', 'secondary')}<span>${esc(t('page', { n: current + 1, total }))}</span>${button(t('next'), `${action}:1`, current >= total - 1 ? 'disabled' : '', 'secondary')}</div>`;
}
function textField(key, max, placeholder = key, type = 'text') {
    return `<label>${esc(t(key))}<input type="${type}" name="${key}" maxlength="${max}" placeholder="${esc(t(placeholder))}" value="${esc(state.draft[key] ?? '')}" autocomplete="off"></label>`;
}
function pagedField(key, max, placeholder = key) {
    // A fixed character measure makes the editable page fit even when a keyboard reduces height.
    const capacity = Math.min(
        editorCaps[key] ?? 60,
        innerHeight <= 450 ? 30 : 60,
    );
    const value = state.draft[key] ?? '';
    const pages = Math.max(
        1,
        Math.ceil(value.length / capacity) +
            (value.length % capacity === 0 && value.length < max ? 1 : 0),
    );
    state.editPage = Math.min(state.editPage, pages - 1);
    const start = state.editPage * capacity;
    const part = value.slice(start, start + capacity);
    return `<label>${esc(t(key))}<textarea name="${key}" data-start="${start}" data-end="${start + part.length}" maxlength="${Math.min(capacity, max - start)}" placeholder="${esc(t(placeholder))}">${esc(part)}</textarea></label><div class="pager">${button(t('previous'), 'editPage:-1', state.editPage ? '' : 'disabled', 'secondary')}<span data-counter>${esc(t('characters', { n: value.length, max }))}</span>${button(t('next'), 'editPage:1', start + part.length >= max || !part.length ? 'disabled' : '', 'secondary')}</div>`;
}
function chat() {
    return `<div class="form"><label>${esc(t('chat'))}<input name="chat" maxlength="80" placeholder="${esc(t('chatPlaceholder'))}" value="${esc(state.draft.chat ?? '')}"></label>${button(t('send'), 'chat')}</div>`;
}
function formWrap(content) {
    return `<div class="form">${content}</div>`;
}
function rank() {
    const total = state.stress ? 32 : 8;
    const size = pageSize('rank');
    const pages = Math.ceil(total / size);
    state.page = Math.min(state.page, pages - 1);
    return (
        title('global') +
        `<div class="rank-list">${Array.from(
            { length: Math.min(size, total - state.page * size) },
            (_, i) => {
                const n = state.page * size + i;
                return `<div class="rank-row"><span class="place">${n + 1}</span><span>${esc(names[n % 8])}${n >= 8 ? ` ${n + 1}` : ''}</span><strong>${Math.max(0, 1200 - n * 65)}</strong></div>`;
            },
        ).join('')}</div>` +
        pager(pages)
    );
}
function renderScene() {
    const phone = state.surface === 'phone';
    const admin = state.surface === 'admin';
    switch (state.scene) {
        case 'join':
            return `<div class="split"><div>${title('join')}${sub('joinHelp')}<div class="join-code">K7PX3A</div></div><div class="join-guide"><div class="steps"><b>1</b><span>plummo.fr/join</span></div><div class="steps"><b>2</b><span>${esc(t('enterCode'))}</span></div><div class="steps"><b>3</b><span>${esc(t('ready'))}</span></div></div></div>`;
        case 'code':
        case 'codeError':
        case 'full':
            return (
                title(state.scene === 'full' ? 'full' : 'join') +
                formWrap(
                    state.scene === 'full'
                        ? sub('fullHelp') + go('code', 'retry')
                        : `<label>${esc(t('code'))}<input class="code-input" name="roomCode" maxlength="6" placeholder="K7PX3A" value="${esc(state.draft.roomCode ?? '')}"></label>${state.scene === 'codeError' ? `<p class="notice">${esc(t('codeError'))}</p>` : sub('joinHelp')}${go('identity', 'continue')}`,
                )
            );
        case 'identity':
            return (
                title('identity') +
                formWrap(
                    `<div class="avatar-stage">${mascot(state.color, accessories[state.accessory])}</div>${textField('name', 20)}<div class="swatches">${catalog.colors.map((c, i) => `<button class="swatch" style="background:${c.color}" data-action="color:${i}" aria-label="${esc(c.label[state.locale])}" aria-pressed="${i === state.color}"></button>`).join('')}</div><div class="pager">${button(t('previous'), 'accessory:-1', '', 'secondary')}<span>${esc(catalog.accessories.find((x) => x.id === accessories[state.accessory]).label[state.locale])}</span>${button(t('next'), 'accessory:1', '', 'secondary')}</div>${go('lobby', 'ready')}`,
                )
            );
        case 'lobby':
            return (
                title('lobby') +
                sub('lobbyHelp') +
                (phone
                    ? `<div class="actions">${go('chooseGame', 'chooseGame')}${go('ranking', 'ranking', 'secondary')}</div>${chat()}`
                    : `<div class="join-code">K7PX3A</div>${progress('countReady')}`)
            );
        case 'chooseGame':
            return (
                title('chooseGame') +
                `<div class="choice-grid">${['quiz', 'blind', 'drawing', 'phrase'].map((key) => `<button class="action game-choice" data-action="game:${key}">${icon(key)}${esc(t(key))}</button>`).join('')}</div>`
            );
        case 'packs':
            return (
                title('packs') +
                `<div class="steps"><b>1</b><span>${esc(t(state.game))} · 1–3 packs</span></div>` +
                formWrap(
                    t('packNames')
                        .map((pack, i) =>
                            button(
                                `${state.packs.includes(i) ? '✓ ' : ''}${pack}`,
                                `pack:${i}`,
                                `aria-pressed="${state.packs.includes(i)}"`,
                                state.packs.includes(i) ? '' : 'secondary',
                            ),
                        )
                        .join('') + go('settings', 'continue'),
                )
            );
        case 'settings':
            return (
                title('settings') +
                formWrap(
                    `<label>${esc(t('rounds'))}<select name="rounds"><option>5</option><option>10</option><option>15</option></select></label><label>${esc(t('duration'))}<select name="duration"><option>30 s</option><option>45 s</option><option>60 s</option></select></label>${go('target', 'continue')}`,
                )
            );
        case 'target':
            return (
                title('target') +
                formWrap(
                    `<label>${esc(t('target'))}<select name="target"><option>${esc(t('none'))}</option><option>1 000</option><option>2 000</option><option>5 000</option></select></label>${go('recap', 'continue')}`,
                )
            );
        case 'recap':
            return (
                title('recap') +
                formWrap(
                    `<h2>${esc(t(state.game))}</h2><p>${esc(state.packs.map((i) => t('packNames')[i]).join(' · '))}</p><p>5 ${esc(t('rounds').toLowerCase())} · 30 s</p>${go(state.game === 'phrase' ? 'write' : state.game === 'drawing' ? 'word' : state.game, 'launch')}`,
                )
            );
        case 'quiz':
            return questionBlock() + answerGrid(quizEntries()) + progress();
        case 'blind':
            return (
                title('listen', true) +
                sub('listenHelp', true) +
                (phone
                    ? ''
                    : `<div class="waveform" aria-hidden="true">${Array.from({ length: 36 }, (_, i) => `<i style="--bar:${18 + ((i * 37) % 68)}px"></i>`).join('')}</div>`) +
                answerGrid(blindEntries(), 'blind') +
                progress()
            );
        case 'sent':
            return title('sent') + sub('sentHelp') + chat();
        case 'quizReveal':
        case 'blindReveal':
            return (
                title('correct') +
                `<div class="hero-number">+125</div>${sub('correctHelp')}` +
                (phone ? go('sent', 'chat', 'secondary') : progress())
            );
        case 'word':
            return (
                title('pickWord', true) +
                formWrap(
                    t('words')
                        .map((word) => button(word, 'go:drawing'))
                        .join(''),
                )
            );
        case 'drawing':
            return (
                title(phone ? 'drawTitle' : 'guess', true) +
                (phone ? `<p class="notice">${esc(t('word'))}</p>` : '') +
                '<div class="draw-area"><canvas aria-label="Drawing"></canvas></div>' +
                (phone
                    ? `<div class="tools">${button(t('undo'), 'undo', '', 'secondary')}${button(t('erase'), 'clear', '', 'secondary')}<input type="color" name="ink" aria-label="${esc(t('color'))}" value="#6950ac"></div>`
                    : progress())
            );
        case 'guess':
        case 'artistMissing':
            return (
                title(state.scene === 'guess' ? 'guess' : 'artistMissing') +
                (state.scene === 'artistMissing'
                    ? sub('artistMissingHelp')
                    : '') +
                (phone
                    ? formWrap(
                          `${textField('guess', 100, 'guessPlaceholder')}${button(t('try'), 'guess')}${state.note ? `<p class="notice">${esc(state.note)}</p>` : ''}${state.scene === 'artistMissing' ? button(t('skip'), 'skip', '', 'secondary') + sub('skipCount') : ''}`,
                      )
                    : '<div class="draw-area"><canvas aria-label="Drawing"></canvas></div>')
            );
        case 'found':
            return (
                title('found') + `<div class="hero-number">+150</div>${chat()}`
            );
        case 'write':
            return (
                title('write', true) +
                `<p class="summary">${esc(t('prefix'))}</p>` +
                formWrap(
                    pagedField('phrase', 150, 'suffixPlaceholder') +
                        go('sent', 'validate'),
                )
            );
        case 'presentation':
            return (
                title('presentation') +
                `<div class="reader"><div class="reader-body summary" id="presentation-text">${esc(phraseEntries()[state.page % 8].text)}</div>${button(t('read'), 'readPresentation', '', 'secondary')}${pager(8)}</div>`
            );
        case 'vote': {
            const items = phraseEntries();
            const size = pageSize('phrase');
            const pages = Math.ceil(8 / size);
            state.page = Math.min(state.page, pages - 1);
            return (
                title('vote', true) +
                answerGrid(
                    items.slice(state.page * size, (state.page + 1) * size),
                    'phrase',
                ) +
                (pages > 1 ? pager(pages) : progress('countVote'))
            );
        }
        case 'phraseReveal':
        case 'results':
            return (
                title('results') +
                `<div class="podium"><div class="podium-step"><b>2</b><strong>Tom</strong><span>960</span></div><div class="podium-step"><b>1</b><strong>Léa</strong><span>1 200</span></div><div class="podium-step"><b>3</b><strong>Nora</strong><span>825</span></div></div><div class="actions">${go('ranking', 'global', 'secondary')}${phone ? go('chooseGame', 'chooseGame') : ''}</div>`
            );
        case 'ranking':
            return rank();
        case 'pause':
            return (
                title('pause') +
                sub('pauseHelp') +
                (phone
                    ? go('resume', 'resume') + chat()
                    : '<div class="hero-number">Ⅱ</div>')
            );
        case 'resume':
            return title('resumeCount') + '<div class="hero-number">5</div>';
        case 'waiting':
        case 'connection':
        case 'closed':
        case 'goal':
            return (
                title(state.scene) +
                sub(
                    `${state.scene}Help` in messages[state.locale]
                        ? `${state.scene}Help`
                        : 'global',
                ) +
                (phone
                    ? state.scene === 'goal'
                        ? `<div class="actions">${go('target', 'extend')}${go('reset', 'reset', 'secondary')}</div>`
                        : state.scene === 'closed'
                          ? go('code', 'newRoom')
                          : chat()
                    : state.scene === 'goal'
                      ? '<div class="hero-number">1 200</div>'
                      : '')
            );
        case 'reset':
            return (
                title('resetConfirm') +
                sub('resetHelp') +
                `<div class="actions">${go('lobby', 'confirm', 'danger')}${go('goal', 'back', 'secondary')}</div>`
            );
        case 'login':
            return (
                title('login') +
                formWrap(
                    `${textField('email', 254, 'email', 'email')}${textField('password', 100, 'password', 'password')}${go('catalog', 'loginAction')}`,
                )
            );
        case 'catalog':
            return catalogView();
        case 'content':
            return (
                title('create') +
                formWrap(
                    `<label>${esc(t('filter'))}<select name="adminType">${['quiz', 'blind', 'drawing', 'phrase'].map((key) => `<option value="${key}" ${state.adminType === key ? 'selected' : ''}>${esc(t(key))}</option>`).join('')}</select></label>${pagedField(state.adminType === 'quiz' ? 'question' : state.adminType === 'blind' ? 'songTitle' : state.adminType === 'drawing' ? 'word' : 'prompt', state.adminType === 'quiz' ? 500 : state.adminType === 'blind' ? 200 : state.adminType === 'drawing' ? 100 : 240)}${go('choices', 'continue')}`,
                )
            );
        case 'choices':
            return (
                title('choices') +
                formWrap(
                    state.adminType === 'quiz'
                        ? `<label>${esc(t('correctAnswer'))}<select name="correctAnswer">${t(
                              'answers',
                          )
                              .map(
                                  (answer, i) =>
                                      `<option>${String.fromCharCode(65 + i)} · ${esc(answer)}</option>`,
                              )
                              .join(
                                  '',
                              )}</select></label>${pagedField('choices', 200)}${go('tags', 'continue')}`
                        : state.adminType === 'blind'
                          ? `${pagedField('artist', 200)}<label>${esc(t('audio'))}<input name="audio" type="file" accept="audio/mpeg,audio/wav,audio/ogg,audio/mp4"></label>${go('tags', 'continue')}`
                          : go('tags', 'continue'),
                )
            );
        case 'tags':
            return (
                title('tags') +
                formWrap(
                    t('packNames')
                        .map((name, i) =>
                            button(
                                `${state.tag === i ? '✓ ' : ''}${name}`,
                                `tag:${i}`,
                                '',
                                state.tag === i ? '' : 'secondary',
                            ),
                        )
                        .join('') + go('preview', 'continue'),
                )
            );
        case 'preview':
            return (
                title('validation') +
                `<div class="reader"><div class="reader-body summary" data-preview></div>${button(t('read'), 'readDraft', '', 'secondary')}</div><div class="actions">${button(t('publish'), 'publish')}${go('content', 'edit', 'secondary')}</div>`
            );
        case 'upload':
            return (
                title('import') +
                formWrap(
                    `<label>${esc(t('upload'))}<input name="table" type="file" accept=".csv,.xlsx"></label>${sub('synthetic')}${go('files', 'continue')}`,
                )
            );
        case 'files':
            return (
                title('files') +
                formWrap(
                    `<label>${esc(t('audio'))}<input name="audioBatch" type="file" accept=".mp3,.wav,.ogg,.m4a" multiple></label>${go('importPreview', 'continue')}`,
                )
            );
        case 'importPreview':
            return (
                title('importPreview') +
                `<p class="notice">${esc(t('validRows'))} · ${esc(t('errorRows'))}</p><div class="rank-list"><div class="rank-row"><span>19</span><span>${esc(t('required'))}</span><strong>!</strong></div><div class="rank-row"><span>20</span><span>${esc(t('missingAudio'))}</span><strong>!</strong></div></div>${go('importReport', 'importValid')}`
            );
        case 'importReport':
            return (
                title('importReport') +
                `<div class="hero-number">18</div>${sub('validRows')}${go('catalog', 'catalog')}`
            );
        default:
            return title('lobby');
    }
}
function catalogView() {
    const size = pageSize('catalog');
    const total = 24;
    const pages = Math.ceil(total / size);
    state.page = Math.min(state.page, pages - 1);
    const search = state.draft.search ?? '';
    const rows = Array.from({ length: total }, (_, i) => ({
        id: i,
        text: t('songs')[i % 8] + ' · ' + t('artists')[i % 8],
    })).filter((x) => x.text.toLowerCase().includes(search.toLowerCase()));
    return (
        title('catalog') +
        `<div class="library-tools"><input name="search" placeholder="${esc(t('search'))}" aria-label="${esc(t('search'))}" value="${esc(search)}"><select name="catalogType" aria-label="${esc(t('filter'))}"><option>${esc(t('all'))}</option>${['quiz', 'blind', 'drawing', 'phrase'].map((key) => `<option>${esc(t(key))}</option>`).join('')}</select></div><div class="admin-list">${rows
            .slice(state.page * size, (state.page + 1) * size)
            .map(
                (item) =>
                    `<div class="catalog-row"><span class="summary">${esc(state.stress ? longText(item.text, 200) : item.text)}</span><span class="status">${esc(t('published'))}</span>${button(t('edit'), `edit:${item.id}`, '', 'secondary')}</div>`,
            )
            .join(
                '',
            )}</div>${pager(Math.max(1, Math.ceil(rows.length / size)))}<div class="actions">${go('content', 'create')}${go('upload', 'import', 'secondary')}</div>`
    );
}
function menu() {
    const list = scenes[state.surface];
    const size = innerHeight <= 450 ? 6 : 8;
    const pages = Math.ceil(list.length / size);
    return (
        title('screens') +
        `<div class="scene-grid">${list
            .slice(state.menuPage * size, (state.menuPage + 1) * size)
            .map((scene) => go(scene, titles[scene] ?? scene, 'secondary'))
            .join('')}</div>` +
        pager(pages, 'menuPage')
    );
}
function readView() {
    return `<div class="titlebar"><h1>${esc(state.reader.title)}</h1>${button(t('close'), 'readerClose', '', 'secondary')}</div><div class="reader"><div class="reader-body" id="reader-text"></div><div class="pager">${button(t('previous'), 'readerPage:-1', '', 'secondary')}<span id="reader-count"></span>${button(t('next'), 'readerPage:1', '', 'secondary')}</div></div>`;
}
function focusSnapshot() {
    const el = document.activeElement;
    if (!root.contains(el)) return null;
    const selector = el.name
        ? `[name="${el.name}"]`
        : el.dataset.action
          ? `[data-action="${el.dataset.action}"]`
          : null;
    return selector
        ? { selector, start: el.selectionStart, end: el.selectionEnd }
        : null;
}
function restoreFocus(snapshot) {
    const el = snapshot && root.querySelector(snapshot.selector);
    if (!el) return;
    el.focus({ preventScroll: true });
    if (snapshot.start != null && ['INPUT', 'TEXTAREA'].includes(el.tagName)) {
        try {
            el.setSelectionRange(
                Math.min(snapshot.start, el.value.length),
                Math.min(snapshot.end, el.value.length),
            );
        } catch {
            /* Non-text input. */
        }
    }
}
function fitEditor(snapshot = focusSnapshot()) {
    const el = root.querySelector('textarea');
    if (!el || el.scrollHeight <= el.clientHeight + 1) return false;
    const value = el.value;
    let low = 1,
        high = value.length,
        fit = 1;
    while (low <= high) {
        const mid = Math.floor((low + high) / 2);
        el.value = value.slice(0, mid);
        if (el.scrollHeight <= el.clientHeight + 1) {
            fit = mid;
            low = mid + 1;
        } else high = mid - 1;
    }
    editorCaps[el.name] = fit;
    el.value = value;
    render(snapshot);
    return true;
}
function render(snapshot = focusSnapshot()) {
    document.documentElement.lang = state.locale;
    document.title = t('title');
    const sceneContent = state.reader
        ? readView()
        : state.menu
          ? menu()
          : renderScene();
    root.innerHTML = `<nav class="lab" aria-label="${esc(t('prototype'))}"><span>${esc(t('prototype'))}</span><select name="surface" aria-label="${esc(t('screens'))}">${['tv', 'phone', 'admin'].map((s) => `<option value="${s}" ${state.surface === s ? 'selected' : ''}>${esc(t(s))}</option>`).join('')}</select><button data-action="menu">${esc(t('screens'))}</button><button data-action="stress" aria-pressed="${state.stress}">${esc(t('stress'))}</button><button data-action="locale" aria-label="${state.locale === 'fr' ? 'English' : 'Français'}">${state.locale.toUpperCase()}</button></nav><main class="world ${['drawing', 'guess', 'artistMissing'].includes(state.scene) ? 'draw-world' : ''} ${['quizReveal', 'blindReveal', 'phraseReveal', 'results'].includes(state.scene) ? 'bounce' : ''}" data-surface="${state.surface}" data-scene="${state.scene}"><header class="hud"><a class="logo" href="#${state.surface}/lobby">plummo<span aria-hidden="true">.</span></a><span class="hud-label">${esc(t(state.surface === 'admin' ? 'admin' : ['blind', 'drawing', 'write', 'vote', 'presentation'].includes(state.scene) ? (['write', 'vote', 'presentation'].includes(state.scene) ? 'phrase' : state.scene) : state.game))}</span><div class="roomcode"><span>${esc(t('room'))}</span><strong>K7PX3A</strong></div></header><section class="arena ${state.scene === 'drawing' || (state.scene === 'artistMissing' && state.surface === 'tv') ? 'draw-layout' : ''} ${state.menu ? 'scene-menu' : ''}" aria-label="${esc(t(titles[state.scene] ?? state.scene))}">${sceneContent}</section><footer class="foot">${footerContent()}${cast()}</footer></main>`;
    if (state.reader) fitReader();
    if (fitEditor(snapshot)) return;
    if (state.scene === 'preview')
        document.querySelector('[data-preview]').textContent =
            state.draft.question ||
            state.draft.songTitle ||
            state.draft.word ||
            state.draft.prompt ||
            t('quizQuestion');
    if (document.querySelector('canvas')) initCanvas();
    history.replaceState(null, '', `#${state.surface}/${state.scene}`);
    restoreFocus(snapshot);
}
function footerContent() {
    if (state.menu || state.reader) return '';
    if (state.surface === 'tv')
        return `<p class="sub">${esc(t('eight'))}<br>${esc(t('lobbyHelp'))}</p>`;
    const order = scenes[state.surface];
    const index = order.indexOf(state.scene);
    return `${index > 0 ? button('', 'previousScene', `aria-label="${esc(t('previous'))}"`, 'secondary').replace('</button>', backIcon + '</button>') : ''}<span class="sub">${esc(t('scene', { n: index + 1, total: order.length }))}</span>`;
}
function navigate(scene) {
    state.scene = scene;
    state.menu = false;
    state.reader = null;
    state.page = 0;
    state.editPage = 0;
    state.note = '';
    render();
}
function showReader(title, text) {
    state.reader = { title, text, pages: [], trigger: focusSnapshot() };
    state.readerPage = 0;
    render();
    const heading = document.querySelector('.arena h1');
    heading.tabIndex = -1;
    heading.focus({ preventScroll: true });
}
function fitReader() {
    const host = document.querySelector('#reader-text');
    const text = state.reader.text;
    const pages = [];
    let start = 0;
    // Measure with the exact rendered font and area; preserve every character, including spaces.
    while (start < text.length) {
        let low = 1,
            high = text.length - start,
            fit = 1;
        while (low <= high) {
            const mid = Math.floor((low + high) / 2);
            host.textContent = text.slice(start, start + mid);
            if (host.scrollHeight <= host.clientHeight + 1) {
                fit = mid;
                low = mid + 1;
            } else high = mid - 1;
        }
        const chunk = text.slice(start, start + fit);
        const word = chunk.lastIndexOf(' ');
        if (word > fit * 0.65 && start + fit < text.length) fit = word + 1;
        pages.push(text.slice(start, start + fit));
        start += fit;
    }
    state.reader.pages = pages.length ? pages : [''];
    state.readerPage = Math.min(
        state.readerPage,
        state.reader.pages.length - 1,
    );
    host.textContent = state.reader.pages[state.readerPage];
    document.querySelector('#reader-count').textContent = t('page', {
        n: state.readerPage + 1,
        total: state.reader.pages.length,
    });
    document.querySelector('[data-action="readerPage:-1"]').disabled =
        state.readerPage === 0;
    document.querySelector('[data-action="readerPage:1"]').disabled =
        state.readerPage === state.reader.pages.length - 1;
}
function initCanvas() {
    const canvas = document.querySelector('canvas');
    const box = canvas.getBoundingClientRect();
    const ratio = devicePixelRatio || 1;
    canvas.width = Math.max(1, Math.round(box.width * ratio));
    canvas.height = Math.max(1, Math.round(box.height * ratio));
    const ctx = canvas.getContext('2d');
    ctx.scale(ratio, ratio);
    ctx.lineWidth = 5;
    ctx.lineCap = 'round';
    ctx.lineJoin = 'round';
    const paint = (stroke) => {
        ctx.strokeStyle = stroke.color;
        ctx.beginPath();
        stroke.points.forEach(([x, y], i) => {
            if (i) ctx.lineTo(x * box.width, y * box.height);
            else ctx.moveTo(x * box.width, y * box.height);
        });
        ctx.stroke();
    };
    if (state.strokes.length) state.strokes.forEach(paint);
    else if (state.surface === 'tv' || state.scene !== 'drawing') {
        ctx.strokeStyle = '#6950ac';
        ctx.beginPath();
        ctx.moveTo(box.width * 0.38, box.height * 0.7);
        ctx.lineTo(box.width * 0.5, box.height * 0.15);
        ctx.lineTo(box.width * 0.62, box.height * 0.7);
        ctx.closePath();
        ctx.moveTo(box.width * 0.44, box.height * 0.7);
        ctx.lineTo(box.width * 0.44, box.height * 0.83);
        ctx.moveTo(box.width * 0.56, box.height * 0.7);
        ctx.lineTo(box.width * 0.56, box.height * 0.83);
        ctx.stroke();
    }
    if (state.surface !== 'phone' || state.scene !== 'drawing') return;
    let stroke = null;
    canvas.addEventListener('pointerdown', (event) => {
        canvas.setPointerCapture(event.pointerId);
        stroke = {
            color: document.querySelector('[name=ink]').value,
            points: [],
        };
        state.strokes.push(stroke);
        add(event);
    });
    function add(event) {
        if (!stroke) return;
        stroke.points.push([
            (event.clientX - box.left) / box.width,
            (event.clientY - box.top) / box.height,
        ]);
        paint(stroke);
    }
    canvas.addEventListener('pointermove', add);
    canvas.addEventListener('pointerup', () => {
        stroke = null;
    });
    canvas.addEventListener('pointercancel', () => {
        stroke = null;
    });
}
root.addEventListener('click', (event) => {
    const target = event.target.closest('[data-action]');
    if (!target || target.disabled) return;
    const [action, arg, typeIndex] = target.dataset.action.split(':');
    if (action === 'go') return navigate(arg);
    if (action === 'menu') {
        state.menu = !state.menu;
        state.menuPage = 0;
        state.reader = null;
    }
    if (action === 'stress') state.stress = !state.stress;
    if (action === 'locale') state.locale = state.locale === 'fr' ? 'en' : 'fr';
    if (action === 'previousScene') {
        const list = scenes[state.surface];
        return navigate(list[Math.max(0, list.indexOf(state.scene) - 1)]);
    }
    if (action === 'color') state.color = Number(arg);
    if (action === 'accessory')
        state.accessory =
            (state.accessory + Number(arg) + accessories.length) %
            accessories.length;
    if (action === 'game') {
        state.game = arg;
        return navigate('packs');
    }
    if (action === 'pack') {
        const n = Number(arg);
        state.packs = state.packs.includes(n)
            ? state.packs.length > 1
                ? state.packs.filter((x) => x !== n)
                : state.packs
            : [...state.packs, n].slice(0, 3);
    }
    if (action === 'tag') state.tag = Number(arg);
    if (action === 'answer') {
        state.answer = Number(arg);
        return navigate('sent');
    }
    if (action === 'vote') return navigate('sent');
    if (action === 'readPresentation')
        return showReader(
            t('presentation'),
            phraseEntries()[state.page % 8].text,
        );
    if (action === 'readQuestion') return showReader(t('question'), question());
    if (action === 'readAnswer') {
        const entries =
            arg === 'blind'
                ? blindEntries()
                : arg === 'phrase'
                  ? phraseEntries()
                  : quizEntries();
        const entry = entries[Number(typeIndex)];
        return showReader(
            String.fromCharCode(65 + Number(typeIndex)),
            entry.text + (entry.secondary ? '\n\n' + entry.secondary : ''),
        );
    }
    if (action === 'readerClose') {
        const trigger = state.reader.trigger;
        state.reader = null;
        render(trigger);
        return;
    }
    if (action === 'readerPage') {
        state.readerPage += Number(arg);
        fitReader();
        return;
    }
    if (action === 'page') state.page = Math.max(0, state.page + Number(arg));
    if (action === 'menuPage')
        state.menuPage = Math.max(0, state.menuPage + Number(arg));
    if (action === 'editPage')
        state.editPage = Math.max(0, state.editPage + Number(arg));
    if (action === 'chat') {
        state.note = (
            document.querySelector('[name=chat]')?.value || t('chatBubble')
        ).slice(0, 80);
    }
    if (action === 'guess') {
        if ((state.draft.guess || '').toLowerCase() === t('word').toLowerCase())
            return navigate('found');
        state.note = t('near');
    }
    if (action === 'skip') state.note = t('skipCount');
    if (action === 'undo') {
        state.strokes.pop();
    }
    if (action === 'clear') {
        state.strokes = [];
    }
    if (action === 'edit') {
        state.draft.question = t('quizQuestion');
        return navigate('content');
    }
    if (action === 'readDraft')
        return showReader(
            t('preview'),
            state.draft.question ||
                state.draft.songTitle ||
                state.draft.word ||
                state.draft.prompt ||
                t('quizQuestion'),
        );
    if (action === 'publish') return navigate('catalog');
    render();
});
root.addEventListener('input', (event) => {
    const el = event.target;
    if (!el.name || ['surface', 'ink', 'adminType'].includes(el.name)) return;
    if (el.tagName === 'TEXTAREA') {
        const start = Number(el.dataset.start);
        const old = state.draft[el.name] ?? '';
        const end = Number(el.dataset.end);
        state.draft[el.name] = old.slice(0, start) + el.value + old.slice(end);
        el.dataset.end = String(start + el.value.length);
        const max =
            el.name === 'phrase'
                ? 150
                : el.name === 'question'
                  ? 500
                  : el.name === 'prompt'
                    ? 240
                    : el.name === 'word'
                      ? 100
                      : 200;
        document.querySelector('[data-counter]').textContent = t('characters', {
            n: state.draft[el.name].length,
            max,
        });
        document.querySelector('[data-action="editPage:1"]').disabled =
            !el.value.length || start + el.value.length >= max;
        fitEditor();
    } else {
        state.draft[el.name] = el.value;
        if (el.name === 'name') state.nickname = el.value || 'Léa';
    }
});
root.addEventListener('change', (event) => {
    const el = event.target;
    if (el.name === 'surface') {
        state.surface = el.value;
        return navigate(
            state.surface === 'tv'
                ? 'quiz'
                : state.surface === 'phone'
                  ? 'code'
                  : 'catalog',
        );
    }
    if (el.name === 'adminType') {
        state.adminType = el.value;
        state.editPage = 0;
        render();
    }
    if (el.name === 'search') render();
});
function readHash() {
    const [surface, scene] = location.hash.slice(1).split('/');
    if (scenes[surface]?.includes(scene)) {
        state.surface = surface;
        state.scene = scene;
    }
}
readHash();
window.addEventListener('hashchange', () => {
    readHash();
    render();
});
let resizeTimer;
function resize() {
    document.documentElement.style.setProperty(
        '--viewport',
        `${window.visualViewport?.height ?? innerHeight}px`,
    );
    clearTimeout(resizeTimer);
    resizeTimer = setTimeout(render, 100);
}
window.visualViewport?.addEventListener('resize', resize);
window.addEventListener('resize', resize);
document.documentElement.style.setProperty(
    '--viewport',
    `${window.visualViewport?.height ?? innerHeight}px`,
);
window.mockups = {
    scenes,
    state,
    setScene(surface, scene, stress = false) {
        state.surface = surface;
        state.stress = stress;
        navigate(scene);
    },
    showReader,
};
render();
