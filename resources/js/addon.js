import ReverseRelationship from './components/fieldtypes/ReverseRelationship.vue'
import ReverseRelationshipFieldSelect from './components/fieldtypes/ReverseRelationshipFieldSelect.vue'

Statamic.booting(() => {
    Statamic.$components.register('reverse_relationship-fieldtype', ReverseRelationship)
    Statamic.$components.register('reverse_relationship_field_select-fieldtype', ReverseRelationshipFieldSelect)
});
