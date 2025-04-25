import StructuredDataBuilder from './components/fieldtypes/StructuredDataBuilder.vue';
import StructuredDataPreview from './components/fieldtypes/StructuredDataPreview.vue';
import StructuredDataObjectBuilder from './components/fieldtypes/StructuredDataObjectBuilder.vue';
import AvailableVariables from './components/fieldtypes/AvailableVariables.vue';

Statamic.booting(() => {
    Statamic.component('structured_data_builder-fieldtype', StructuredDataBuilder);
    Statamic.component('structured_data_preview-fieldtype', StructuredDataPreview);
    Statamic.component('structured_data_object_builder-fieldtype', StructuredDataObjectBuilder);
    Statamic.component('structured_data_available_variables-fieldtype', AvailableVariables);
});
