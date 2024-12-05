<?php

    /**
     * PawTunes Project - Open Source Radio Player
     *
     * @author       Jacky (Jaka Prasnikar)
     * @email        jacky@prahec.com
     * @website      https://prahec.com
     * @repository   https://github.com/Jackysi/pawtunes
     * This file is part of the PawTunes open-source project.
     * Contributions and feedback are welcome! Visit the repository or website for more details.
     * @TODO         needs a rewrite sometime
     */
    class Forms {

        /**
         * @var string|null Stores the generated HTML.
         */
        private ?string $html = null;

        /**
         * @var array List of form inputs.
         */
        public array $fields = [];


        /**
         * Append a form field to the list.
         *
         * @param array|string $field The field configuration array or HTML string.
         *
         * @return void
         */
        public function append( $field ): void {

            $this->fields[] = $field;

        }


        /**
         * Clear all stored fields and HTML.
         *
         * @return void
         */
        public function clear(): void {

            $this->fields = [];
            $this->html = null;

        }


        /**
         * Generate the form HTML from the list of fields.
         *
         * @param array|null $fields      Optional. A specific list of fields to generate.
         * @param mixed|null $forcedValue Optional. A value to force into the fields.
         *
         * @return void
         */
        public function generateForm( $fields = null, $forcedValue = null ): void {
            $fields = $fields ?? $this->fields;

            foreach ( $fields as $field ) {
                if ( !is_array( $field ) ) {
                    echo $field;
                    continue;
                }

                echo $this->add( $field, $forcedValue );
            }
        }


        /**
         * Add a field and return its HTML representation.
         *
         * @param array      $options     Field options array.
         * @param mixed|null $forcedValue Optional. A value to force into the field.
         *
         * @return string The field's HTML output.
         */
        public function add( array $options, $forcedValue = null ): string {

            $output = $this->field( $options, $forcedValue );
            $this->html .= $output;
            return $output;

        }


        /**
         * Generate the HTML for a form field based on the options provided.
         *
         * @param array      $options    Field options array.
         * @param mixed|null $forceValue Optional. A value to force into the field.
         *
         * @return string The generated HTML for the field.
         */
        public function field( array $options, $forceValue = null ): string {

            // Default field options
            $defaults = [
                'type'       => 'text',
                'label-full' => false,
                'class'      => 'col-sm-6',
            ];

            $o = array_merge( $defaults, $options );

            $output = '';
            $fieldName = $o[ 'name' ] ?? null;
            $fieldValue = $forceValue ?? $_POST[ $fieldName ] ?? null;

            // Begin form-group div
            $output .= '<div class="form-group">';

            // Add label if provided
            if ( !empty( $o[ 'label' ] ) ) {
                $labelClass = $o[ 'label-full' ] ? 'col-sm-12' : 'col-sm-2 control-label';
                $labelStyle = isset( $o[ 'label-left' ] ) ? 'style="width: auto !important;"' : '';
                $labelFor = $fieldName ? 'for="' . $fieldName . '"' : '';

                $output .= sprintf(
                    '<label %s class="%s" %s>%s</label>',
                    $labelStyle,
                    $labelClass,
                    $labelFor,
                    $o[ 'label' ]
                );
            }

            // Handle multiple fields in a single row
            if ( isset( $o[ 'multi' ] ) && is_array( $o[ 'multi' ] ) ) {

                foreach ( $o[ 'multi' ] as $multiField ) {
                    $output .= $this->add( $multiField, $forceValue );
                }

            } else {

                // Generate field-specific HTML
                $output .= $this->generateFieldHtml( $o, $fieldValue );
            }

            // Add description/help text if provided
            if ( !empty( $o[ 'description' ] ) && !in_array( $o[ 'type' ], [ 'checkbox', 'radio' ] ) ) {

                $output .= $o[ 'label-full' ] ? '<div class="col-sm-12">' : '';
                $output .= '<div class="help-block">' . $o[ 'description' ] . '</div>';
                $output .= $o[ 'label-full' ] ? '</div>' : '';

            }

            // Close form-group div
            $output .= '</div>';

            return $output;

        }


        /**
         * Generate HTML for specific field types.
         *
         * @param array $o          Field options array.
         * @param mixed $fieldValue The value of the field.
         *
         * @return string The generated HTML for the field.
         */
        private function generateFieldHtml( array $o, $fieldValue ): string {

            $output = '';
            $fieldName = $o[ 'name' ] ?? '';
            $fieldId = $fieldName;
            $fieldClass = $o[ 'class' ] ?? '';
            $extras = $this->buildAttributes( $o );

            switch ( $o[ 'type' ] ) {
                case 'text':
                case 'password':
                case 'number':
                case 'url':
                case 'email':
                    $inputType = $o[ 'type' ];
                    $valueAttr = isset( $fieldValue ) ? 'value="' . $fieldValue . '"' : '';
                    $output .= sprintf(
                        '<div class="%s"><input type="%s" name="%s" id="%s" class="form-control" %s %s></div>',
                        $fieldClass,
                        $inputType,
                        $fieldName,
                        $fieldId,
                        $valueAttr,
                        $extras
                    );
                    break;

                case 'textarea':
                    $heightStyle = isset( $o[ 'height' ] ) ? 'style="min-height: ' . (int) $o[ 'height' ] . 'px;"' : '';
                    $output .= sprintf(
                        '<div class="%s"><textarea name="%s" id="%s" class="form-control" %s %s>%s</textarea></div>',
                        $fieldClass,
                        $fieldName,
                        $fieldId,
                        $heightStyle,
                        $extras,
                        $fieldValue ?? ''
                    );
                    break;

                case 'select':
                    $output .= sprintf(
                        '<div class="%s"><select name="%s" id="%s" class="form-control" %s>',
                        $fieldClass,
                        $fieldName,
                        $fieldId,
                        $extras
                    );
                    if ( isset( $o[ 'options' ] ) && is_array( $o[ 'options' ] ) ) {
                        foreach ( $o[ 'options' ] as $optValue => $optLabel ) {
                            $selected = ( $fieldValue == $optValue ) ? ' selected' : '';
                            $output .= sprintf(
                                '<option value="%s"%s>%s</option>',
                                $optValue,
                                $selected,
                                $optLabel
                            );
                        }
                    }
                    $output .= '</select></div>';
                    break;

                case 'checkbox':
                case 'radio':
                    $inputType = $o[ 'type' ];
                    $checked = ( isset( $fieldValue ) && $fieldValue == $o[ 'value' ] ) ? ' checked' : '';
                    $output .= sprintf(
                        '<div class="%s"><div class="%s"><label for="%s" tabindex="0">' .
                        '<input type="%s" name="%s" id="%s" value="%s"%s %s>' .
                        '<span class="icon fa fa-check"></span> <span class="description">%s</span></label></div></div>',
                        $fieldClass,
                        $inputType,
                        $fieldId,
                        $inputType,
                        $fieldName,
                        $fieldId,
                        $o[ 'value' ],
                        $checked,
                        $extras,
                        $o[ 'description' ] ?? ''
                    );
                    break;

                case 'file':
                    $output .= sprintf(
                        '<div class="%s"><div class="file-input">' .
                        '<input type="file" name="%s" id="%s">' .
                        '<div class="input-group">' .
                        '<input type="text" class="form-control file-name %s" %s>' .
                        '<div class="input-group-btn">' .
                        '<a href="#" class="btn btn-danger"><i class="icon fa fa-folder-open"></i> Browse</a>' .
                        '</div></div></div></div>',
                        $fieldClass,
                        $fieldName,
                        $fieldId,
                        $fieldClass,
                        $extras
                    );
                    break;

                case 'static':
                    $staticValue = $o[ 'value' ] ?? $fieldValue ?? '';
                    $output .= sprintf(
                        '<div class="%s"><p class="form-control-static">%s</p></div>',
                        $fieldClass,
                        $staticValue
                    );
                    break;

                default:
                    // Handle unsupported field types if necessary
                    break;
            }

            return $output;

        }


        /**
         * Build additional attributes for an input field.
         *
         * @param array $o Field options array.
         *
         * @return string A string of additional attributes.
         */
        private function buildAttributes( array $o ): string {

            $attributes = [];
            $possibleAttributes = [
                'size'         => 'maxlength',
                'placeholder'  => 'placeholder',
                'required'     => 'required',
                'reset'        => 'allow-reset',
                'autocomplete' => 'autocomplete',
                'min'          => 'min',
                'max'          => 'max',
                'step'         => 'step',
                'pattern'      => 'pattern',
                'readonly'     => 'readonly',
                'disabled'     => 'disabled',
            ];

            foreach ( $possibleAttributes as $key => $attrName ) {
                if ( isset( $o[ $key ] ) ) {

                    $attrValue = is_bool( $o[ $key ] ) ? '' : $o[ $key ];
                    $attributes[] = $attrValue !== '' ? sprintf( '%s="%s"', $attrName, $attrValue ) : $attrName;

                }
            }

            return implode( ' ', $attributes );

        }

    }