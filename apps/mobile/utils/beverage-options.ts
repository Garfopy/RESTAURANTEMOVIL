import type { Modificador, Platillo } from '@amare/types';

export const BEVERAGE_OPTION_MODIFIER_ID = -30;

const BEVERAGE_OPTION_MODIFIER_NAME = 'Opciones de bebida';
const BEVERAGE_OPTION_NAMES = ['Con hielo', 'Sin hielo', 'Al tiempo'] as const;

const BEVERAGE_KEYWORDS = [
  'bebida',
  'bebidas',
  'bevida',
  'bevidas',
  'bar',
  'drink',
  'drinks',
  'agua',
  'aguas',
  'refresco',
  'refrescos',
  'jugo',
  'jugos',
  'limonada',
  'naranjada',
  'cerveza',
  'cervezas',
  'vino',
  'vinos',
  'coctel',
  'cocteles',
  'cocktail',
  'cafe',
  'chai',
  'infusion',
];

function normalize(value: unknown): string {
  return String(value ?? '')
    .normalize('NFD')
    .replace(/[\u0300-\u036f]/g, '')
    .toLowerCase();
}

function includesKeyword(value: string, keyword: string): boolean {
  return new RegExp(`(^|[^a-z0-9])${keyword}([^a-z0-9]|$)`).test(value);
}

export function isBeverageProduct(product: Platillo | null | undefined): boolean {
  if (!product) return false;

  const categoryName = normalize(product.categoria_nombre);
  if (categoryName && BEVERAGE_KEYWORDS.some((keyword) => includesKeyword(categoryName, keyword))) {
    return true;
  }

  const productName = normalize(product.nombre);
  return BEVERAGE_KEYWORDS.some((keyword) => includesKeyword(productName, keyword));
}

export function getBeverageOptionModifier(product: Platillo | null | undefined): Modificador | null {
  if (!isBeverageProduct(product)) return null;

  return {
    id: BEVERAGE_OPTION_MODIFIER_ID,
    nombre: BEVERAGE_OPTION_MODIFIER_NAME,
    tipo: 'radio',
    requerido: false,
    min_selecciones: 0,
    max_selecciones: 1,
    categoria: 'extra',
    opciones: BEVERAGE_OPTION_NAMES.map((name, index) => ({
      id: BEVERAGE_OPTION_MODIFIER_ID - index - 1,
      modificador_id: BEVERAGE_OPTION_MODIFIER_ID,
      nombre: name,
      precio_extra: 0,
      activo: true,
      tipo_modificador: 'extra',
      max_cantidad: 1,
    })),
  };
}

export function appendBeverageOptions(
  product: Platillo | null | undefined,
  modifiers: Modificador[] = []
): Modificador[] {
  const beverageOptions = getBeverageOptionModifier(product);
  if (!beverageOptions) return modifiers;
  if (modifiers.some((modifier) => modifier.id === BEVERAGE_OPTION_MODIFIER_ID)) return modifiers;
  return [beverageOptions, ...modifiers];
}

export function getBeverageSelectionLabel(selections: { modificador_id: number; opciones: { opcion_nombre: string }[] }[]): string | null {
  const optionName = selections
    .find((selection) => selection.modificador_id === BEVERAGE_OPTION_MODIFIER_ID)
    ?.opciones[0]?.opcion_nombre;

  return optionName ? `Bebida: ${optionName}` : null;
}

export function appendBeverageSelectionToNotes(
  notes: string | null | undefined,
  selections: { modificador_id: number; opciones: { opcion_nombre: string }[] }[]
): string {
  const baseNotes = String(notes ?? '').trim();
  const beverageNote = getBeverageSelectionLabel(selections);
  if (!beverageNote) return baseNotes;
  return baseNotes ? `${baseNotes}\n${beverageNote}` : beverageNote;
}
