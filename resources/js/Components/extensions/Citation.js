import { Node, mergeAttributes } from '@tiptap/core';

export const Citation = Node.create({
    name: 'citation',
    group: 'inline',
    inline: true,
    atom: true,
    selectable: true,
    draggable: false,

    addAttributes() {
        return {
            reference_id: {
                default: null,
                parseHTML: (element) => {
                    const id = element.getAttribute('data-reference-id');
                    return id ? parseInt(id, 10) : null;
                },
                renderHTML: (attributes) => {
                    if (!attributes.reference_id) return {};
                    return { 'data-reference-id': attributes.reference_id };
                },
            },
            label: {
                default: '',
                parseHTML: (element) => element.getAttribute('data-label') || element.textContent?.trim() || '',
                renderHTML: (attributes) => {
                    return { 'data-label': attributes.label || '' };
                },
            },
            reference_key: {
                default: '',
                parseHTML: (element) => element.getAttribute('data-reference-key') || '',
                renderHTML: (attributes) => {
                    return attributes.reference_key ? { 'data-reference-key': attributes.reference_key } : {};
                },
            },
        };
    },

    parseHTML() {
        return [
            { tag: 'span[data-type="citation"]' },
            { tag: 'span[data-reference-id]' },
        ];
    },

    renderHTML({ HTMLAttributes, node }) {
        const label = node.attrs.label || '(Sitasi)';
        return [
            'span',
            mergeAttributes(this.options.HTMLAttributes, HTMLAttributes, {
                'data-type': 'citation',
                class: 'academic-citation inline-flex items-center px-1.5 py-0.5 mx-0.5 rounded text-xs font-semibold bg-indigo-50 text-indigo-700 border border-indigo-200 hover:bg-indigo-100 hover:border-indigo-300 transition-colors cursor-pointer select-none',
                title: 'Klik untuk melihat detail referensi sumber',
            }),
            label,
        ];
    },

    addCommands() {
        return {
            insertCitation: (attrs) => ({ chain }) => {
                return chain()
                    .insertContent({
                        type: this.name,
                        attrs,
                    })
                    .insertContent(' ')
                    .run();
            },
        };
    },
});

export default Citation;
