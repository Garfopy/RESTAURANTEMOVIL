import { StyleSheet } from 'react-native';

export const Typography = {
  displayXL:  { fontSize: 34, lineHeight: 41 },
  displayLG:  { fontSize: 28, lineHeight: 34 },
  h1:         { fontSize: 22, lineHeight: 28 },
  h2:         { fontSize: 20, lineHeight: 24 },
  h3:         { fontSize: 17, lineHeight: 22 },
  bodyLG:     { fontSize: 17, lineHeight: 24 },
  body:       { fontSize: 15, lineHeight: 22 },
  bodySM:     { fontSize: 13, lineHeight: 18 },
  caption:    { fontSize: 12, lineHeight: 16 },
  tiny:       { fontSize: 11, lineHeight: 14 },
  price:      { fontSize: 18, lineHeight: 24 },
  priceLG:    { fontSize: 24, lineHeight: 30 },
} as const;

export const FontFamily = {
  regular:   'Inter_400Regular',
  medium:    'Inter_500Medium',
  semibold:  'Inter_600SemiBold',
  bold:      'Inter_700Bold',
  heading:   'PlayfairDisplay_700Bold',
  headingItalic: 'PlayfairDisplay_700BoldItalic',
} as const;
