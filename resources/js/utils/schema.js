export function formatOutput(schema) {
    const result = {};

    if (schema.specialProps) {
        if (schema.specialProps.context) {
            result['@context'] = schema.specialProps.context;
        }
        if (schema.specialProps.type) {
            result['@type'] = schema.specialProps.type;
        }
        if (schema.specialProps.id) {
            result['@id'] = schema.specialProps.id;
        }
    }

    if (schema.fields && Array.isArray(schema.fields)) {
        for (const field of schema.fields) {
            if (!field.key) continue;

            if (field.type === 'array' && field.values) {
                result[field.key] = field.values;
            } else if (field.type === 'object' && field.value) {
                result[field.key] = formatOutput(field.value);
            } else if (field.type === 'object_array' && field.values) {
                result[field.key] = field.values.map(value => formatOutput(value));
            } else if (field.type === 'replicator_object_array' && field.config) {
                const config = field.config || {};

                if (config.flat === true && config.flat_key_field && config.flat_value_field) {
                    const exampleKey = `{{ ${config.flat_key_field} }}`;
                    const exampleValue = `{{ ${config.flat_value_field} }}`;
                    result[field.key] = {
                        [exampleKey]: exampleValue,
                    };
                } else {
                    const sample = {};
                    const mappings = Array.isArray(config.mappings) ? config.mappings : [];

                    mappings.forEach(mapping => {
                        if (!mapping.key) {
                            return;
                        }
                        if (mapping.mode === 'static') {
                            sample[mapping.key] = mapping.static ?? '';
                        } else if (mapping.mode === 'field') {
                            sample[mapping.key] = `{{ ${mapping.field || 'field'} }}`;
                        } else if (mapping.mode === 'nested_replicator') {
                            sample[mapping.key] = [{}];
                        } else {
                            sample[mapping.key] = '';
                        }
                    });

                    result[field.key] = [sample];
                }
            } else {
                result[field.key] = field.value ?? null;
            }
        }
    }

    return result;
}

export function formatSchemaJson(data, space = 2) {
    try {
        if (Array.isArray(data)) {
            const formattedData = data.map(schema => formatOutput(schema));
            return JSON.stringify(formattedData, null, space);
        }
        const formattedData = formatOutput(data);
        return JSON.stringify(formattedData, null, space);
    } catch (e) {
        return JSON.stringify(data, null, space);
    }
}
