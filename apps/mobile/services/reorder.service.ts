import { getDishById } from './menu.service';
import { useCartStore } from '../store/cart.store';
import type { Pedido, PedidoItem } from '@amare/types';

export interface ReorderResult {
  addedCount: number;
  skippedCount: number;
}

function parseOrderItemNotes(item: PedidoItem): string {
  if (item.notas && item.notas.startsWith('{')) {
    try {
      const parsed = JSON.parse(item.notas);
      return parsed.notas || '';
    } catch {
      return '';
    }
  }
  return item.notas || '';
}

/**
 * Agrega al carrito los platillos de un pedido anterior, obteniendo el
 * producto vigente del menú (precio/disponibilidad puede haber cambiado).
 * Los modificadores/extras originales no se reconstruyen: el usuario puede
 * volver a personalizarlos desde el carrito o el detalle del platillo.
 */
export async function reorderPastOrder(order: Pedido): Promise<ReorderResult> {
  const { addItem } = useCartStore.getState();
  let addedCount = 0;
  let skippedCount = 0;

  for (const item of order.items ?? []) {
    try {
      const platillo = await getDishById(order.restaurante_id, item.platillo_id);
      addItem(platillo, item.cantidad, [], parseOrderItemNotes(item));
      addedCount += 1;
    } catch {
      skippedCount += 1;
    }
  }

  return { addedCount, skippedCount };
}
