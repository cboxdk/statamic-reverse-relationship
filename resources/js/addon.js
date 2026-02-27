import ReverseRelationship from './components/fieldtypes/ReverseRelationship.vue'

Statamic.booting(() => {
    Statamic.$components.register('reverse_relationship-fieldtype', ReverseRelationship)
});
