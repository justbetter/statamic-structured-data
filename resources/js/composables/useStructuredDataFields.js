const KEY_SANITIZER_REGEX = /[^a-zA-Z0-9@:-]/g;

export const sanitizeStructuredDataKey = key => {
    if (typeof key !== 'string') {
        return '';
    }

    return key.replace(KEY_SANITIZER_REGEX, '');
};

export const validateStructuredDataKey = field => {
    if (!field || typeof field !== 'object') {
        return;
    }

    field.key = sanitizeStructuredDataKey(field.key);
};

export const addStructuredDataArrayValue = (field, { objectArrayType } = {}) => {
    if (!field || typeof field !== 'object') {
        return;
    }

    if (!Array.isArray(field.values)) {
        field.values = [];
    }

    if (objectArrayType && field.type === objectArrayType) {
        field.values.push({
            specialProps: {
                type: '',
                id: '',
            },
            fields: [],
        });

        return;
    }

    field.values.push('');
};

export const removeStructuredDataArrayValue = (field, valueIndex) => {
    if (!field || typeof field !== 'object' || !Array.isArray(field.values)) {
        return;
    }

    field.values.splice(valueIndex, 1);
};

export const handleStructuredDataFieldTypeChange = (
    field,
    {
        objectType = 'object',
        arrayType = 'array',
        objectArrayType,
        dataObjectType,
        replicatorObjectArrayType = 'replicator_object_array',
    } = {},
) => {
    if (!field || typeof field !== 'object') {
        return;
    }

    if (field.type === objectType) {
        field.value = {
            specialProps: {
                type: '',
                id: '',
            },
            fields: [],
        };

        return;
    }

    if (field.type === arrayType) {
        field.values = [];

        return;
    }

    if (objectArrayType && field.type === objectArrayType) {
        field.values = [];

        return;
    }

    if (dataObjectType && field.type === dataObjectType) {
        field.value = '';

        return;
    }

    if (field.type === replicatorObjectArrayType) {
        field.config = {
            replicator_field: '',
            set: '',
            mappings: [],
        };
        field.values = [];

        return;
    }

    field.value = '';
};
