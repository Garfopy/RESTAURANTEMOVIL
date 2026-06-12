# Accessibility Pattern Template — WCAG AA Compliance

## Quick Reference for all screens

### Pattern 1: Button/Link
```tsx
<TouchableOpacity
  onPress={handler}
  accessibilityLabel="[Action] [Object]"     // e.g., "Add to favorites", "Open menu"
  accessibilityRole="button"                  // or "link"
  accessibilityHint="What happens?"           // Optional: additional context
  testID="[unique-id]"                        // e.g., "favorite-btn"
>
  <Text>Label</Text>
</TouchableOpacity>
```

### Pattern 2: Selectable Item (Radio Button)
```tsx
<TouchableOpacity
  onPress={() => setSelected(id)}
  accessibilityLabel="Select [Option name]"   // e.g., "Select delivery"
  accessibilityRole="radio"
  accessibilityState={{ selected: isSelected }}
  testID={`option-${id}`}
>
  <Text>{option}</Text>
</TouchableOpacity>
```

### Pattern 3: Icon-Only Button
```tsx
<TouchableOpacity
  onPress={handler}
  accessibilityLabel="[Full description]"    // NOT "heart icon"
  accessibilityRole="button"
  testID="icon-btn"
>
  <Ionicons name="heart" size={24} />
</TouchableOpacity>
```

### Pattern 4: Button Component (pre-configured)
```tsx
<Button
  label="Submit"
  onPress={handler}
  accessibilityLabel="Submit form"           // Use if different from label
  testID="submit-btn"
  // Button.tsx handles role="button" automatically
/>
```

### Pattern 5: Form Input
```tsx
<TextInput
  placeholder="Email"
  accessibilityLabel="Email input"
  testID="email-input"
/>
```

---

## Good vs Bad Examples

### ❌ BAD
```tsx
<TouchableOpacity onPress={addToCart}>
  <Ionicons name="add-circle" size={32} />
</TouchableOpacity>
// Screen reader says: "button, add circle"
// User confusion: what does this do?
```

### ✅ GOOD
```tsx
<TouchableOpacity
  onPress={addToCart}
  accessibilityLabel="Agregar al carrito"
  testID="add-to-cart-btn"
>
  <Ionicons name="add-circle" size={32} />
</TouchableOpacity>
// Screen reader says: "button, Agregar al carrito"
// User clarity: immediately knows what clicking does
```

---

## Checklist for Each Screen

- [ ] Back button: `accessibilityLabel="Volver atrás"`
- [ ] All clickable elements have `accessibilityLabel`
- [ ] All buttons have `accessibilityRole="button"`
- [ ] All radio/toggle selections have `accessibilityRole="radio"` + `accessibilityState={{ selected }}`
- [ ] All elements have unique `testID`
- [ ] Icon-only buttons have descriptive labels (not icon names)
- [ ] Form inputs have associated labels
- [ ] Long actions have `accessibilityHint` for additional context

---

## Files Already Completed ✅
- [x] components/ui/Button.tsx
- [x] app/checkout/order-type.tsx
- [x] app/checkout/payment.tsx

---

## Files to Complete 📋

### Phase 2.4: Auth Screens (HIGH PRIORITY)
- [ ] app/(auth)/email-login.tsx
- [ ] app/(auth)/register.tsx

### Phase 2.5: Component Library
- [ ] components/cards/ProductCard.tsx
- [ ] components/cards/CategoryCard.tsx
- [ ] components/shared/CartButton.tsx
- [ ] components/shared/StoreFAB.tsx
- [ ] app/branch-selector.tsx
- [ ] And more...

---

## Common Patterns by Screen Type

### Checkout / Multi-Step
- Back button: "Volver atrás"
- Action buttons: "[Action] [Object]" (e.g., "Seleccionar a domicilio")
- Continue buttons: "Continuar a [next step]"

### Auth / Forms
- Back button: "Volver atrás"
- Text inputs: "[Field name] input" (e.g., "Email input")
- Submit: "Iniciar sesión" / "Registrarse"
- Toggle password visibility: "Mostrar contraseña" / "Ocultar contraseña"

### Product Listings
- Product cards: "[Product name] at $[price]"
- Favorite button: "Agregar a favoritos" or "Eliminar de favoritos" (dynamic)
- Cart button: "Agregar [product] al carrito"

### Profile / Settings
- Edit button: "Editar [field]"
- Delete button: "Eliminar [item]"
- Save button: "Guardar cambios"

---

## Testing with Screen Reader

### iOS (VoiceOver)
1. Settings > Accessibility > VoiceOver > On
2. Navigate with two-finger Z to go back
3. Two-finger swipe right to navigate forward

### Android (TalkBack)
1. Settings > Accessibility > TalkBack > On
2. Use volume keys + right/left swipe to navigate

---

## Resources
- [React Native Accessibility Docs](https://reactnative.dev/docs/accessibility)
- [WCAG 2.1 Level AA](https://www.w3.org/WAI/WCAG21/quickref/)
- [Apple Accessibility Guidelines](https://www.apple.com/accessibility/)
