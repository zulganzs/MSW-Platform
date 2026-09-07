import './bootstrap';
import Alpine from 'alpinejs';
window.Alpine = Alpine;

Alpine.store('toast', {
    items: [],
    add(message, type = 'info') {
        const id = Date.now();
        this.items.push({ id, message, type });
        setTimeout(() => this.remove(id), 3000);
    },
    remove(id) {
        this.items = this.items.filter(item => item.id !== id);
    }
});

Alpine.start();
