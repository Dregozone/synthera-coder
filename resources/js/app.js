import hljs from 'highlight.js/lib/core';
import bash from 'highlight.js/lib/languages/bash';
import css from 'highlight.js/lib/languages/css';
import ini from 'highlight.js/lib/languages/ini';
import javascript from 'highlight.js/lib/languages/javascript';
import json from 'highlight.js/lib/languages/json';
import markdown from 'highlight.js/lib/languages/markdown';
import php from 'highlight.js/lib/languages/php';
import phpTemplate from 'highlight.js/lib/languages/php-template';
import plaintext from 'highlight.js/lib/languages/plaintext';
import scss from 'highlight.js/lib/languages/scss';
import sql from 'highlight.js/lib/languages/sql';
import typescript from 'highlight.js/lib/languages/typescript';
import xml from 'highlight.js/lib/languages/xml';
import yaml from 'highlight.js/lib/languages/yaml';

// Only the languages the changes panel names (DiffService::language()).
Object.entries({
    bash, css, ini, javascript, json, markdown, php, 'php-template': phpTemplate,
    plaintext, scss, sql, typescript, xml, yaml,
}).forEach(([name, language]) => hljs.registerLanguage(name, language));

// `<code x-highlight data-language="php">` colours its text as that language.
// Lines of a diff are coloured one at a time, so each is highlighted on its own.
document.addEventListener('alpine:init', () => {
    window.Alpine.directive('highlight', (el) => {
        const language = el.dataset.language;

        if (! language || ! hljs.getLanguage(language)) {
            return;
        }

        el.innerHTML = hljs.highlight(el.textContent, { language, ignoreIllegals: true }).value;
        el.classList.add('hljs');
    });
});
