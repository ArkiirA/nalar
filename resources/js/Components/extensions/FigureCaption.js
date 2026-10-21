import { Node, mergeAttributes } from '@tiptap/core';

export const FigureCaption = Node.create({
    name: 'figureCaption',
    group: 'block',
    content: 'inline*',
    defining: true,

    parseHTML() {
        return [
            { tag: 'p[data-type="figure-caption"]' },
            { tag: 'figcaption' },
            { tag: 'div[data-type="caption"]' },
        ];
    },

    renderHTML({ HTMLAttributes }) {
        return [
            'p',
            mergeAttributes(this.options.HTMLAttributes, HTMLAttributes, {
                'data-type': 'figure-caption',
                class: 'academic-caption text-center text-xs md:text-sm italic text-gray-700 my-2 font-serif',
            }),
            0,
        ];
    },

    addCommands() {
        return {
            toggleFigureCaption: () => ({ commands }) => {
                return commands.toggleNode(this.name, 'paragraph');
            },
            setFigureCaption: () => ({ commands }) => {
                return commands.setNode(this.name);
            },
        };
    },
});

export default FigureCaption;
