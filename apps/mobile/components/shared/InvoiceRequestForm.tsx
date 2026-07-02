import React from 'react';
import { StyleSheet, Switch, Text, TextInput, View } from 'react-native';
import type { FiscalData } from '../../services/fiscal.service';

type Props = {
  enabled: boolean;
  required: boolean;
  data: FiscalData;
  saveToProfile: boolean;
  disabled?: boolean;
  onRequiredChange: (value: boolean) => void;
  onDataChange: (data: FiscalData) => void;
  onSaveToProfileChange: (value: boolean) => void;
};

const FIELD_META: Array<{
  key: keyof FiscalData;
  label: string;
  placeholder: string;
  keyboardType?: 'default' | 'email-address' | 'number-pad';
}> = [
  { key: 'rfc', label: 'RFC', placeholder: 'XAXX010101000' },
  { key: 'nombre_fiscal', label: 'Razon social / nombre fiscal', placeholder: 'Nombre fiscal' },
  { key: 'regimen_fiscal', label: 'Regimen fiscal', placeholder: '612' },
  { key: 'codigo_postal', label: 'Codigo postal fiscal', placeholder: '00000', keyboardType: 'number-pad' },
  { key: 'uso_cfdi', label: 'Uso CFDI', placeholder: 'G03' },
  { key: 'email', label: 'Email', placeholder: 'correo@dominio.com', keyboardType: 'email-address' },
];

export function InvoiceRequestForm({
  enabled,
  required,
  data,
  saveToProfile,
  disabled,
  onRequiredChange,
  onDataChange,
  onSaveToProfileChange,
}: Props) {
  if (!enabled) return null;

  const updateField = (key: keyof FiscalData, value: string) => {
    const shouldUppercase = key !== 'email' && key !== 'nombre_fiscal';
    onDataChange({
      ...data,
      [key]: shouldUppercase ? value.toUpperCase() : value,
    });
  };

  return (
    <View style={styles.box}>
      <View style={styles.header}>
        <View style={styles.headerCopy}>
          <Text style={styles.title}>Requiere factura</Text>
        </View>
        <Switch value={required} onValueChange={onRequiredChange} disabled={disabled} />
      </View>

      {required ? (
        <View style={styles.form}>
          {FIELD_META.map((field) => (
            <View key={field.key} style={styles.field}>
              <Text style={styles.label}>{field.label}</Text>
              <TextInput
                value={String(data[field.key] ?? '')}
                onChangeText={(value) => updateField(field.key, value)}
                placeholder={field.placeholder}
                placeholderTextColor="#94A3B8"
                style={styles.input}
                editable={!disabled}
                autoCapitalize={field.key === 'email' ? 'none' : 'characters'}
                keyboardType={field.keyboardType ?? 'default'}
              />
            </View>
          ))}

          <View style={styles.saveRow}>
            <View style={styles.saveCopy}>
              <Text style={styles.saveTitle}>Guardar estos datos</Text>
              <Text style={styles.saveSubtitle}>Se precargaran en tus proximos pagos.</Text>
            </View>
            <Switch value={saveToProfile} onValueChange={onSaveToProfileChange} disabled={disabled} />
          </View>
        </View>
      ) : null}
    </View>
  );
}

const styles = StyleSheet.create({
  box: {
    marginBottom: 12,
    padding: 14,
    borderRadius: 8,
    borderWidth: 1,
    borderColor: '#E2E8F0',
    backgroundColor: '#FFFFFF',
    gap: 12,
  },
  header: {
    minHeight: 48,
    flexDirection: 'row',
    alignItems: 'center',
    gap: 12,
  },
  headerCopy: { flex: 1, minWidth: 0 },
  title: { color: '#111827', fontSize: 16, fontWeight: '800' },
  subtitle: { marginTop: 2, color: '#64748B', fontSize: 12, fontWeight: '600' },
  form: { gap: 10 },
  field: { gap: 5 },
  label: { color: '#475569', fontSize: 12, fontWeight: '800' },
  input: {
    minHeight: 46,
    borderRadius: 8,
    borderWidth: 1,
    borderColor: '#CBD5E1',
    backgroundColor: '#F8FAFC',
    paddingHorizontal: 12,
    color: '#111827',
    fontSize: 14,
    fontWeight: '700',
  },
  saveRow: {
    minHeight: 48,
    flexDirection: 'row',
    alignItems: 'center',
    gap: 12,
    paddingTop: 2,
  },
  saveCopy: { flex: 1, minWidth: 0 },
  saveTitle: { color: '#111827', fontSize: 14, fontWeight: '800' },
  saveSubtitle: { marginTop: 2, color: '#64748B', fontSize: 12, fontWeight: '600' },
});
