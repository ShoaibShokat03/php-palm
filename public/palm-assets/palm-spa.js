(() => {
   
    const root = document.getElementById('spa-root');
    const views = window.__PALM_VIEWS__ || {};
    const routeMap = window.__PALM_ROUTE_MAP__ || {};
    const initialComponents = window.__PALM_COMPONENTS__ || [];
    const components = {};
    const appState = window.__PALM_APP_STATE__ || {};
    let currentSlug = root.dataset.spaCurrent || null;
    const executedScripts = new Set();

    if (!root || Object.keys(views).length === 0) {
        return;
    }

    const decodePath = (href) => {
        const url = new URL(href, window.location.origin);
        let path = url.pathname;
        if (path.length > 1) {
            path = path.replace(/\/+$/, '');
            if (path === '') path = '/';
        }
        return path;
    };

    const updateMeta = (meta = {}) => {
        if (meta.description) {
            let tag = document.querySelector('meta[name="description"]');
            if (!tag) {
                tag = document.createElement('meta');
                tag.setAttribute('name', 'description');
                document.head.appendChild(tag);
            }
            tag.setAttribute('content', meta.description);
        }
    };

    const updateActiveLinks = (path) => {
        document.querySelectorAll('[palm-spa-link]').forEach((link) => {
            const linkPath = decodePath(link.href);
            link.classList.toggle('is-active', linkPath === path);
        });
    };

    const parseArgs = (raw) => {
        if (!raw) {
            return [];
        }

        try {
            const fn = new Function(`"use strict"; return [${raw}];`);
            return fn();
        } catch (error) {
            console.warn('[Palm SPA] Failed to parse action arguments:', raw, error);
            return [];
        }
    };

    const parseToken = (token) => {
        if (!token) {
            return null;
        }
        const parts = token.split('::');
        if (parts.length !== 2) {
            return null;
        }
        return {
            componentId: parts[0],
            slotId: parts[1],
        };
    };

    const applyAttributes = (node, attrs = {}) => {
        Object.entries(attrs).forEach(([key, value]) => {
            if (!key) {
                return;
            }
            if (typeof value === 'boolean') {
                if (value) {
                    node.setAttribute(key, key);
                }
                return;
            }
            node.setAttribute(key, value);
        });
    };

    const registerComponent = (meta, container, options = {}) => {
        const { slug = currentSlug, fromServer = false } = options;
        if (!meta || !container) {
            return;
        }

        const state = {};
        const stateMeta = {};
        (meta.states || []).forEach(({ id, value, global, key }) => {
            const slotMeta = {
                global: Boolean(global),
                key: key || null,
            };
            stateMeta[id] = slotMeta;

            if (slotMeta.global && slotMeta.key) {
                const shouldAdoptServerValue =
                    fromServer || !Object.prototype.hasOwnProperty.call(appState, slotMeta.key);
                if (shouldAdoptServerValue) {
                    appState[slotMeta.key] = value;
                }
                state[id] = appState[slotMeta.key];
                return;
            }

            state[id] = value;
        });

        const bindings = {};
        container.querySelectorAll('[data-palm-bind]').forEach((node) => {
            const [componentId, slotId] = (node.dataset.palmBind || '').split('::');
            if (componentId !== meta.id) {
                return;
            }
            if (!bindings[slotId]) {
                bindings[slotId] = [];
            }
            bindings[slotId].push(node);
        });

        container.querySelectorAll('[data-palm-action]').forEach((node) => {
            node.dataset.palmComponent = meta.id;
        });

        const classBindings = {};
        const attrBindings = {};

        const addBinding = (store, slotId, binding) => {
            if (!store[slotId]) {
                store[slotId] = [];
            }
            store[slotId].push(binding);
        };

        container.querySelectorAll('[data-palm-toggle-class]').forEach((node) => {
            const raw = node.getAttribute('data-palm-toggle-class') || '';
            raw.split(',').map((entry) => entry.trim()).filter(Boolean).forEach((entry) => {
                const parts = entry.split(':').map((p) => p.trim());
                if (parts.length < 2) {
                    return;
                }
                const tokenMap = parseToken(parts[0]);
                if (!tokenMap || tokenMap.componentId !== meta.id) {
                    return;
                }
                const className = parts[1];
                const when = parts[2] === 'falsy' ? 'falsy' : 'truthy';
                addBinding(classBindings, tokenMap.slotId, {
                    node,
                    className,
                    when,
                });
            });
        });

        container.querySelectorAll('[data-palm-attr]').forEach((node) => {
            const raw = node.getAttribute('data-palm-attr') || '';
            raw.split(',').map((entry) => entry.trim()).filter(Boolean).forEach((entry) => {
                const parts = entry.split(':').map((p) => p.trim());
                if (parts.length < 2) {
                    return;
                }
                const tokenMap = parseToken(parts[0]);
                if (!tokenMap || tokenMap.componentId !== meta.id) {
                    return;
                }
                const attribute = parts[1];
                const truthyValue = parts[2] ?? '__bool_true__';
                const falsyValue = parts[3] ?? '__remove__';
                addBinding(attrBindings, tokenMap.slotId, {
                    node,
                    attribute,
                    truthyValue,
                    falsyValue,
                });
            });
        });

        components[meta.id] = {
            id: meta.id,
            state,
            stateMeta,
            bindings,
            classBindings,
            attrBindings,
            actions: meta.actions || {},
            slug: slug || '__root__',
        };
        Object.keys(state).forEach((slotId) => updateBindings(meta.id, slotId));
    };

    const hydrateInitialComponents = () => {
        initialComponents.forEach((meta) => {
            const container = document.querySelector(`[data-palm-component="${meta.id}"]`);
            registerComponent(meta, container, { slug: currentSlug, fromServer: true });
        });
    };

    const applyClassBindings = (component, slotId) => {
        const bindings = component.classBindings[slotId] || [];
        if (!bindings.length) {
            return;
        }

        const isTruthy = Boolean(component.state[slotId]);
        bindings.forEach(({ node, className, when }) => {
            const shouldHave = when === 'falsy' ? !isTruthy : isTruthy;
            node.classList.toggle(className, shouldHave);
        });
    };

    const applyAttrBindings = (component, slotId) => {
        const bindings = component.attrBindings[slotId] || [];
        if (!bindings.length) {
            return;
        }

        const isTruthy = Boolean(component.state[slotId]);
        bindings.forEach(({ node, attribute, truthyValue, falsyValue }) => {
            const value = isTruthy ? truthyValue : falsyValue;
            if (value === '__remove__' || value === '' || value === null) {
                node.removeAttribute(attribute);
                return;
            }
            if (value === '__bool_true__') {
                node.setAttribute(attribute, attribute);
                return;
            }
            if (value === '__bool_false__') {
                node.removeAttribute(attribute);
                return;
            }
            node.setAttribute(attribute, value);
        });
    };

    const updateBindings = (componentId, slotId) => {
        const component = components[componentId];
        if (!component) {
            return;
        }
        const nodes = component.bindings[slotId] || [];
        nodes.forEach((node) => {
            node.textContent = component.state[slotId] ?? '';
        });

        applyClassBindings(component, slotId);
        applyAttrBindings(component, slotId);
    };

    const runAction = (componentId, actionName, args = []) => {
        const component = components[componentId];
        if (!component) {
            return;
        }

        const ops = component.actions[actionName] || [];
        if (!ops.length) {
            return;
        }

        const resolveValue = (value) => {
            if (value && typeof value === 'object' && value.type === 'arg') {
                return args[value.index];
            }
            return value;
        };

        ops.forEach((op) => {
            const slotId = op.slot;
            switch (op.type) {
                case 'increment':
                    component.state[slotId] = (Number(component.state[slotId]) || 0) + Number(op.value || 1);
                    break;
                case 'decrement':
                    component.state[slotId] = (Number(component.state[slotId]) || 0) - Number(op.value || 1);
                    break;
                case 'toggle':
                    component.state[slotId] = !component.state[slotId];
                    break;
                case 'set':
                    component.state[slotId] = resolveValue(op.value);
                    break;
                default:
                    break;
            }
            updateBindings(componentId, slotId);

            const slotMeta = component.stateMeta[slotId];
            if (slotMeta?.global && slotMeta.key) {
                appState[slotMeta.key] = component.state[slotId];
            }
        });
    };

    const runPalmScripts = (scripts = []) => {
        scripts.forEach((script) => {
            if (!script || !script.code) {
                return;
            }
            const hash = script.hash || '';
            const once = script.once !== false;
            if (once && hash && executedScripts.has(hash)) {
                return;
            }

            const tag = document.createElement('script');
            applyAttributes(tag, script.attrs || {});
            if (hash) {
                tag.dataset.palmScript = hash;
            }
            tag.dataset.palmOnce = once ? '1' : '0';
            tag.textContent = script.code;
            const target = script.target === 'head' ? document.head : document.body;
            target.appendChild(tag);

            if (once && hash) {
                executedScripts.add(hash);
            }
        });
    };

    const registerBootScripts = () => {
        document.querySelectorAll('script[data-palm-script]').forEach((node) => {
            const hash = node.dataset.palmScript;
            const once = node.dataset.palmOnce !== '0';
            if (hash && once) {
                executedScripts.add(hash);
            }
        });

    };

    const renderSlug = (slug, path, pushState = false) => {
        const payload = views[slug];
        if (!payload) {
            window.location.href = path;
            return;
        }

        const fromServer = Boolean(payload.__fresh);
        if (fromServer) {
            delete payload.__fresh;
        }

        Object.keys(components).forEach((id) => {
            delete components[id];
        });

        root.innerHTML = payload.html;
        root.dataset.spaCurrent = slug;
        currentSlug = slug;

        if (payload.title) {
            document.title = payload.title;
        }
        updateMeta(payload.meta);
        updateActiveLinks(path);

        if (payload.component) {
            const container = root.querySelector(`[data-palm-component="${payload.component.id}"]`);
            registerComponent(payload.component, container, { slug, fromServer });
        }

        if (payload.scripts) {
            runPalmScripts(payload.scripts);
        }

        if (pushState) {
            history.pushState({ slug }, payload.title || document.title, path);
        } else {
            history.replaceState({ slug }, payload.title || document.title, path);
        }
    };

    document.addEventListener('click', (event) => {
        const anchor = event.target.closest('a');
        if (anchor && anchor.hasAttribute('palm-spa-link')) {
            if (
                anchor.target === '_blank' ||
                event.metaKey ||
                event.ctrlKey ||
                event.shiftKey ||
                event.altKey
            ) {
                return;
            }

            const path = decodePath(anchor.href);
            const slug = routeMap[path];
            if (!slug) {
                return;
            }

            event.preventDefault();
            renderSlug(slug, path, true);
            return;
        }

        const trigger = event.target.closest('[data-palm-action]');
        if (!trigger) {
            return;
        }

        const actionName = trigger.dataset.palmAction;
        const componentId = trigger.dataset.palmComponent;
        if (!actionName || !componentId) {
            return;
        }

        event.preventDefault();
        const args = parseArgs(trigger.getAttribute('data-palm-args'));
        runAction(componentId, actionName, args);
    });

    window.addEventListener('popstate', (event) => {
        const slug = event.state?.slug || routeMap[window.location.pathname];
        if (!slug) {
            window.location.reload();
            return;
        }
        renderSlug(slug, window.location.pathname, false);
    });

    const submitSpaForm = async (form) => {
        const method = (form.getAttribute('method') || 'POST').toUpperCase();
        if (method !== 'POST') {
            form.submit();
            return;
        }

        const action = form.getAttribute('action') || window.location.pathname;
        const path = decodePath(action);
        const slug = routeMap[path];

        if (!slug) {
            form.submit();
            return;
        }

        try {
            const response = await fetch(action, {
                method: 'POST',
                body: new FormData(form),
                headers: {
                    'X-Palm-Request': '1',
                },
                credentials: 'same-origin',
            });

            if (!response.ok) {
                throw new Error('Form submission failed');
            }

            const data = await response.json();
            if (!data || !data.slug || !data.payload) {
                throw new Error('Invalid response');
            }

            views[data.slug] = {
                ...data.payload,
                __fresh: true,
            };
            if (data.routeMap) {
                Object.assign(routeMap, data.routeMap);
            }
            renderSlug(data.slug, path, false);
        } catch (error) {
            console.error('[Palm SPA form]', error);
            form.submit();
        }
    };

    document.addEventListener('submit', (event) => {
        const form = event.target.closest('form');
        if (!form) {
            return;
        }

        if (form.dataset.spaForm === 'false') {
            return;
        }

        const method = (form.getAttribute('method') || 'POST').toUpperCase();
        if (method !== 'POST') {
            return;
        }

        event.preventDefault();
        submitSpaForm(form);
    });

    const initialSlug = root.dataset.spaCurrent;
    updateActiveLinks(window.location.pathname);
    history.replaceState({ slug: initialSlug }, document.title, window.location.pathname);
    hydrateInitialComponents();
    registerBootScripts();
})();
