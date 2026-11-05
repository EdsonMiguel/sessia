import { Livewire, Alpine } from '../../vendor/livewire/livewire/dist/livewire.esm';
import { pixPayment } from './pix-payment.js';

Alpine.data('pixPayment', pixPayment);
Livewire.start();
