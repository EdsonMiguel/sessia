import { Livewire, Alpine } from '../../vendor/livewire/livewire/dist/livewire.esm';
import './confirmation-dialog.js';
import { pixPayment } from './pix-payment.js';
import { registerSearchableSelects } from './searchable-select.js';

Alpine.data('pixPayment', pixPayment);
registerSearchableSelects(Livewire);
Livewire.start();
