const { registerBlockType } = wp.blocks;
const { InspectorControls, useBlockProps } = wp.blockEditor;
const { TextControl, PanelBody, PanelRow } = wp.components;
const { useEffect, createElement} = wp.element;
const { ServerSideRender } = wp.editor;

registerBlockType('amsa/speaker-list', {
    edit: (props) => {
        const blockProps = useBlockProps();
        const { attributes, setAttributes, clientId } = props;

        useEffect(() => {
            if (!attributes.blockID) {
                setAttributes({ blockID: clientId });
            }
        }, []);

        console.log(attributes);
        if (attributes.blockID){
            var render=createElement(
                'div',
                props.blockProps,
                createElement(ServerSideRender, {
                    block: 'amsa/speaker-list',
                    attributes: attributes,
                })
            );
        }else{
            var render = "Speaker List block"
        }
        return render;
    },
    save: () => {
        return null; // Rendered server-side
    },
});
