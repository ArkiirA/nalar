import { Node, mergeAttributes } from '@tiptap/core';

export const CheckedClaim = Node.create({
    name: 'checkedClaim',
    group: 'inline',
    inline: true,
    atom: true,
    selectable: true,
    draggable: false,

    addAttributes() {
        return {
            claim_id: {
                default: null,
                parseHTML: (element) => {
                    const id = element.getAttribute('data-claim-id');
                    return id ? parseInt(id, 10) : null;
                },
                renderHTML: (attributes) => {
                    if (!attributes.claim_id) return {};
                    return { 'data-claim-id': attributes.claim_id };
                },
            },
            status: {
                default: 'Supported',
                parseHTML: (element) => element.getAttribute('data-status') || 'Supported',
                renderHTML: (attributes) => ({ 'data-status': attributes.status || 'Supported' }),
            },
            claim_text: {
                default: '',
                parseHTML: (element) => element.getAttribute('data-claim-text') || element.textContent?.trim() || '',
                renderHTML: (attributes) => ({ 'data-claim-text': attributes.claim_text || '' }),
            },
        };
    },

    parseHTML() {
        return [
            { tag: 'span[data-type="checked-claim"]' },
            { tag: 'span[data-claim-id]' },
        ];
    },

    renderHTML({ HTMLAttributes, node }) {
        const status = node.attrs.status || 'Supported';
        const claimText = node.attrs.claim_text || '';

        let badgeClasses = 'bg-gray-100 text-gray-700 border-gray-300';
        let badgeIcon = '❓';

        if (status === 'Supported') {
            badgeClasses = 'bg-emerald-50 text-emerald-800 border-emerald-300';
            badgeIcon = '✓';
        } else if (status === 'Contradicted') {
            badgeClasses = 'bg-rose-50 text-rose-800 border-rose-300';
            badgeIcon = '✗';
        } else if (status === 'Insufficient Evidence') {
            badgeClasses = 'bg-amber-50 text-amber-800 border-amber-300';
            badgeIcon = '⚠';
        }

        return [
            'span',
            mergeAttributes(this.options.HTMLAttributes, HTMLAttributes, {
                'data-type': 'checked-claim',
                class: 'checked-claim-mark inline-flex items-center flex-wrap gap-1 px-1 py-0.5 mx-0.5 rounded border border-dashed border-indigo-200 bg-indigo-50/30 hover:bg-indigo-100/50 cursor-pointer transition select-none',
                title: `Evaluasi bukti klaim: ${status}. Klik untuk rincian bukti.`,
            }),
            ['span', { class: 'text-gray-900 font-serif' }, claimText],
            [
                'span',
                {
                    'data-type': 'claim-badge',
                    class: `inline-flex items-center space-x-0.5 px-1.5 py-0.2 rounded-full text-[10px] font-sans font-bold border ${badgeClasses}`,
                },
                `${badgeIcon} ${status}`,
            ],
        ];
    },

    addCommands() {
        return {
            insertCheckedClaim:
                (attrs) =>
                ({ chain }) => {
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

export default CheckedClaim;
