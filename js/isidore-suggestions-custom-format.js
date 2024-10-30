( function( wp ) {
	const isidoreIcon = wp.element.createElement(
		'svg', 
		{ 
			width: 20, 
			height: 20 
		},
		wp.element.createElement( 'path',
		{ 
			d: "m1.1445 3.2754c-0.14965 3.377 0.92812 5.1002 3.1152 6.7246-2.1871 1.6244-3.2649 3.3476-3.1152 6.7246 3.7332-0.387 5.4995-3.3801 6.0312-4.5957a6 6 0 0 0 5.6211 3.9102 6 6 0 0 0 6 -6 6 6 0 0 0 -6 -6 6 6 0 0 0 -5.6172 3.9141l0.00977-0.054687c-0.51895-1.1999-2.2872-4.2335-6.0449-4.623zm11.652 3.2012a3.5857 3.5857 0 0 1 3.5859 3.584 3.5857 3.5857 0 0 1 -3.5859 3.5859 3.5857 3.5857 0 0 1 -3.584 -3.5859 3.5857 3.5857 0 0 1 3.584 -3.584zm0.02344 2.2012a1.3591 1.3591 0 0 0 -1.3594 1.3594 1.3591 1.3591 0 0 0 1.3594 1.3594 1.3591 1.3591 0 0 0 1.3594 -1.3594 1.3591 1.3591 0 0 0 -1.3594 -1.3594z" 
		}
	));
	var IsidoreSuggestionsCustomButton = function( props ) {
        return wp.element.createElement(
            wp.editor.RichTextToolbarButton, {
                icon: isidoreIcon,
                title: 'Span isidore',
                onClick: function() {
                    props.onChange( wp.richText.toggleFormat(
                        props.value,
                        { type: 'isidore-suggestions-custom-format/isidore-suggestions' }
                    ) );
                },
                isActive: props.isActive,
            }
        );
    }
    wp.richText.registerFormatType(
        'isidore-suggestions-custom-format/isidore-suggestions', {
            title: 'Span isidore',
            tagName: 'span',
            className: 'isidore',
            edit: IsidoreSuggestionsCustomButton,
        }
    );
} )( window.wp );