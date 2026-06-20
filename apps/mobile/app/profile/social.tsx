import React, { useEffect, useMemo, useRef, useState } from 'react';
import {
  ActivityIndicator,
  Alert,
  NativeScrollEvent,
  NativeSyntheticEvent,
  Linking,
  Modal,
  Platform,
  Pressable,
  ScrollView,
  StyleSheet,
  Switch,
  Text,
  TouchableOpacity,
  View,
  useWindowDimensions,
} from 'react-native';
import { SafeAreaView } from 'react-native-safe-area-context';
import { GestureDetector, Gesture } from 'react-native-gesture-handler';
import Animated, {
  useSharedValue,
  useAnimatedStyle,
  withSpring,
  withTiming,
  runOnJS,
} from 'react-native-reanimated';
import { Stack, useLocalSearchParams, useRouter } from 'expo-router';
import { Ionicons } from '@expo/vector-icons';
import { Image } from 'expo-image';
import * as ImagePicker from 'expo-image-picker';
import { LinearGradient } from 'expo-linear-gradient';
import { CardField, useStripe } from '@stripe/stripe-react-native';
import InputField from '../../components/ui/InputField';
import { apiClient, formatImageUrl, getApiError } from '../../services/api';
import { Colors, Shadows } from '../../theme';
import { useBranchStore } from '../../store/branch.store';
import { useTableSessionStore } from '../../store/table-session.store';
import { useUserStore } from '../../store/user.store';

type SelectOption = {
  label: string;
  value: string;
  description?: string;
};

type MesaApiItem = {
  id: number;
  label: string;
  value: string;
};

type SocialProfileResponse = {
  user_id: number;
  nombre: string;
  foto_url: string | null;
  social_photos?: string[] | null;
  edad: number | null;
  sexualidad: string | null;
  genero: string | null;
  descripcion: string | null;
  intereses: string | null;
  que_busca: string | null;
  redes_sociales: string | null;
  mesa?: string | null;
  is_social_active?: boolean | null;
  modo_social?: boolean | null;
  current_restaurante_id?: number | null;
  social_updated_at?: string | null;
  social_consent_accepted_at?: string | null;
  social_consent_version?: string | null;
  requires_social_consent?: boolean | null;
  has_social_profile?: boolean;
};

type ApiEnvelope<T> = {
  success?: boolean;
  message?: string;
  data?: T;
};

type SocialPhotoResponse = {
  foto_url?: string | null;
  social_photos?: string[] | null;
  uploaded_photo_url?: string | null;
};

type SocialStatusResponse = {
  is_social_active?: boolean;
  modo_social?: boolean;
  current_restaurante_id?: number | null;
  mesa?: string | null;
  social_consent_accepted_at?: string | null;
  social_consent_version?: string | null;
  requires_social_consent?: boolean | null;
};

type DinerApiItem = {
  user_id: number;
  nombre: string;
  foto_url: string | null;
  social_photos?: string[] | null;
  edad: number | null;
  genero: string | null;
  sexualidad: string | null;
  descripcion: string | null;
  intereses: string | null;
  que_busca: string | null;
  redes_sociales?: string | null;
  mesa?: string | null;
  relationship_status?: 'none' | 'liked' | 'matched' | string | null;
  matched_at?: string | null;
  match_restaurante_id?: number | null;
  liked_at?: string | null;
  like_restaurante_id?: number | null;
};

type SocialFormState = {
  nombre: string;
  edad: string;
  genero: string;
  sexualidad: string;
  queBusca: string;
  biografia: string;
  instagram: string;
  tiktok: string;
};

type SocialFilterState = {
  edadMin: string;
  edadMax: string;
  genero: string;
  sexualidad: string;
};

type SocialDiner = {
  user_id: number;
  nombre: string;
  foto_url: string | null;
  social_photos: string[];
  edad: number | null;
  genero: string | null;
  sexualidad: string | null;
  descripcion: string | null;
  intereses: string[];
  que_busca: string | null;
  redes_sociales: string | null;
  mesa: string | null;
  relationship_status: 'none' | 'liked' | 'matched';
  matched_at?: string | null;
  match_restaurante_id?: number | null;
  liked_at?: string | null;
  like_restaurante_id?: number | null;
};

type SocialLikeResponse = {
  liked: boolean;
  matched: boolean;
  relationship_status: 'liked' | 'matched';
  match?: DinerApiItem | null;
};

type SocialView = 'discover' | 'matches';

type GiftProduct = {
  id: number;
  nombre: string;
  descripcion?: string | null;
  precio: number;
  icono?: string | null;
  color?: string | null;
  es_regalo?: boolean;
  imagen?: string | null;
  orden?: number;
  tipo?: string | null;
};

const GENDER_OPTIONS: SelectOption[] = [
  { label: 'Hombre', value: 'Hombre' },
  { label: 'Mujer', value: 'Mujer' },
  { label: 'No binario', value: 'No binario' },
  { label: 'Otro', value: 'Otro' },
  { label: 'Prefiero no decirlo', value: 'Prefiero no decirlo' },
];

const SEXUALITY_OPTIONS: SelectOption[] = [
  {
    label: 'Heterosexual',
    value: 'Heterosexual',
    description: 'Atraccion romantica o sexual hacia personas de un genero distinto al propio.',
  },
  {
    label: 'Homosexual',
    value: 'Homosexual',
    description: 'Atraccion romantica o sexual hacia personas del mismo genero.',
  },
  {
    label: 'Bisexual',
    value: 'Bisexual',
    description: 'Atraccion romantica o sexual hacia mas de un genero.',
  },
  {
    label: 'Pansexual',
    value: 'Pansexual',
    description: 'Atraccion romantica o sexual hacia personas sin que el genero sea el factor principal.',
  },
  {
    label: 'Asexual',
    value: 'Asexual',
    description: 'Poca o nula atraccion sexual. Puede existir atraccion romantica, afectiva o emocional.',
  },
  {
    label: 'Prefiero no decirlo',
    value: 'Prefiero no decirlo',
    description: 'Puedes mantener esta informacion privada.',
  },
];

const LOOKING_FOR_OPTIONS: SelectOption[] = [
  { label: 'Amigos', value: 'Amigos' },
  { label: 'Relacion seria', value: 'Relacion seria' },
  { label: 'Nada serio', value: 'Nada serio' },
  { label: 'Conocer gente', value: 'Conocer gente' },
  { label: 'Salir y platicar', value: 'Salir y platicar' },
];

const DEFAULT_INTEREST_OPTIONS = [
  'Coleccionismo',
  'Moda urbana y sneakers',
  'Mascotas',
  'Viajes y turismo',
  'Lectura y literatura',
  'Voluntariado',
  'Teatro',
  'Cerveza artesanal',
  'Videojuegos',
  'Musica',
  'Arte',
  'Cine',
  'Cafe',
  'Comida asiatica',
  'Fitness',
  'Tecnologia',
];

const EMPTY_FORM: SocialFormState = {
  nombre: '',
  edad: '',
  genero: '',
  sexualidad: '',
  queBusca: '',
  biografia: '',
  instagram: '',
  tiktok: '',
};

const EMPTY_FILTERS: SocialFilterState = {
  edadMin: '',
  edadMax: '',
  genero: '',
  sexualidad: '',
};

const SOCIAL_PRIVACY_NOTICE_URL = 'https://amarerestaurant.club/aviso-de-privacidad';

function parseInterestList(value?: string | null): string[] {
  if (!value) return [];
  return value
    .split(',')
    .map((item) => item.trim())
    .filter(Boolean);
}

function parseSocialNetworks(value?: string | null): { instagram: string; tiktok: string } {
  if (!value) return { instagram: '', tiktok: '' };

  try {
    const parsed = JSON.parse(value) as { instagram?: string | null; tiktok?: string | null };
    return {
      instagram: parsed.instagram?.trim() ?? '',
      tiktok: parsed.tiktok?.trim() ?? '',
    };
  } catch {
    const fallback = value.trim();
    return {
      instagram: fallback.startsWith('@') ? fallback : fallback ? `@${fallback}` : '',
      tiktok: '',
    };
  }
}

function stringifySocialNetworks(instagram: string, tiktok: string): string | null {
  const cleanInstagram = instagram.trim();
  const cleanTiktok = tiktok.trim();

  if (!cleanInstagram && !cleanTiktok) return null;

  return JSON.stringify({
    instagram: cleanInstagram || null,
    tiktok: cleanTiktok || null,
  });
}

function isProfileReady(profile: {
  foto_url?: string | null;
  edad?: number | null;
  sexualidad?: string | null;
  genero?: string | null;
  descripcion?: string | null;
}): boolean {
  return Boolean(
    profile.foto_url &&
      profile.edad !== null &&
      profile.edad !== undefined &&
      profile.sexualidad &&
      profile.genero &&
      profile.descripcion?.trim()
  );
}

function resolvePhotoUrl(path?: string | null): string | null {
  if (!path) return null;
  return formatImageUrl(path) ?? path;
}

function normalizePhotoList(value?: (string | null)[] | null, fallback?: string | null): string[] {
  const rawPhotos = Array.isArray(value) ? value : [];
  const photos = rawPhotos
    .map((photo) => resolvePhotoUrl(photo))
    .filter((photo): photo is string => Boolean(photo));
  const fallbackPhoto = resolvePhotoUrl(fallback);

  if (photos.length === 0 && fallbackPhoto) {
    photos.push(fallbackPhoto);
  }

  return Array.from(new Set(photos)).slice(0, 6);
}

function normalizeMesaValue(value: unknown): string | null {
  if (value === null || value === undefined) return null;

  const normalized = String(value).trim();
  return normalized ? normalized : null;
}

function formatHandleLabel(value?: string | null): string | null {
  if (!value) return null;
  const trimmed = value.trim();
  if (!trimmed) return null;
  return trimmed.startsWith('@') ? trimmed : `@${trimmed}`;
}

function formatMatchDate(value?: string | null): string | null {
  if (!value) return null;
  const date = new Date(value.replace(' ', 'T'));
  if (Number.isNaN(date.getTime())) return null;
  return date.toLocaleDateString('es-MX', { day: '2-digit', month: 'short' });
}

function unwrapApiData<T>(payload: ApiEnvelope<T> | T): T {
  if (payload && typeof payload === 'object' && 'data' in payload && payload.data !== undefined) {
    return payload.data as T;
  }

  return payload as T;
}

function moduloIndex(index: number, total: number): number {
  if (total <= 0) return 0;
  return ((index % total) + total) % total;
}

function buildDiner(item: DinerApiItem): SocialDiner {
  const socialPhotos = normalizePhotoList(item.social_photos, item.foto_url);
  const relationshipStatus =
    item.relationship_status === 'liked' || item.relationship_status === 'matched'
      ? item.relationship_status
      : 'none';

  return {
    user_id: Number(item.user_id),
    nombre: item.nombre,
    foto_url: socialPhotos[0] ?? resolvePhotoUrl(item.foto_url),
    social_photos: socialPhotos,
    edad: item.edad ?? null,
    genero: item.genero ?? null,
    sexualidad: item.sexualidad ?? null,
    descripcion: item.descripcion ?? null,
    intereses: parseInterestList(item.intereses),
    que_busca: item.que_busca ?? null,
    redes_sociales: item.redes_sociales ?? null,
    mesa: normalizeMesaValue(item.mesa),
    relationship_status: relationshipStatus,
    matched_at: item.matched_at ?? null,
    match_restaurante_id: item.match_restaurante_id ?? null,
    liked_at: item.liked_at ?? null,
    like_restaurante_id: item.like_restaurante_id ?? null,
  };
}

function isDinerApiItem(value: unknown): value is DinerApiItem {
  return Boolean(
    value &&
      typeof value === 'object' &&
      'user_id' in value &&
      'nombre' in value
  );
}

function normalizeGiftProduct(item: GiftProduct): GiftProduct {
  return {
    ...item,
    id: Number(item.id),
    precio: Number(item.precio ?? 0),
    imagen: resolvePhotoUrl(item.imagen) ?? item.imagen ?? null,
    color: item.color ?? Colors.primary,
  };
}

function ChoiceField({
  label,
  value,
  placeholder,
  options,
  onSelect,
}: {
  label: string;
  value: string;
  placeholder: string;
  options: SelectOption[];
  onSelect: (value: string) => void;
}) {
  const [open, setOpen] = useState(false);
  const selected = options.find((option) => option.value === value);
  const hasDescriptions = options.some((option) => option.description);

  function showOptionInfo() {
    if (selected?.description) {
      Alert.alert(selected.label, selected.description);
      return;
    }

    const descriptionText = options
      .filter((option) => option.description)
      .map((option) => `${option.label}: ${option.description}`)
      .join('\n\n');

    Alert.alert(label, descriptionText || 'Selecciona una opcion para ver mas informacion.');
  }

  return (
    <View style={styles.fieldBlock}>
      <View style={styles.fieldLabelRow}>
        <Text style={styles.fieldLabel}>{label}</Text>
        {hasDescriptions ? (
          <TouchableOpacity
            style={styles.fieldInfoButton}
            activeOpacity={0.75}
            onPress={showOptionInfo}
            accessibilityLabel={`Informacion sobre ${label}`}
            accessibilityRole="button"
          >
            <Ionicons name="information-circle-outline" size={18} color={Colors.primary} />
          </TouchableOpacity>
        ) : null}
      </View>
      <TouchableOpacity
        activeOpacity={0.75}
        onPress={() => setOpen((current) => !current)}
        style={styles.choiceButton}
      >
        <Text style={[styles.choiceValue, !selected && styles.choicePlaceholder]}>
          {selected?.label ?? placeholder}
        </Text>
        <Ionicons name={open ? 'chevron-up' : 'chevron-down'} size={18} color={Colors.textSecondary} />
      </TouchableOpacity>

      {open ? (
        <View style={styles.choiceList}>
          {options.map((option, index) => (
            <TouchableOpacity
              key={option.value}
              activeOpacity={0.75}
              onPress={() => {
                onSelect(option.value);
                setOpen(false);
              }}
              style={[
                styles.choiceItem,
                value === option.value && styles.choiceItemActive,
                index === options.length - 1 && styles.choiceItemLast,
              ]}
            >
              <Text style={[styles.choiceItemText, value === option.value && styles.choiceItemTextActive]}>
                {option.label}
              </Text>
              {value === option.value ? <Ionicons name="checkmark" size={18} color={Colors.primary} /> : null}
            </TouchableOpacity>
          ))}
        </View>
      ) : null}
      {selected?.description ? <Text style={styles.choiceDescription}>{selected.description}</Text> : null}
    </View>
  );
}

function getSexualityDescription(value?: string | null): string | null {
  if (!value) return null;
  return SEXUALITY_OPTIONS.find((option) => option.value === value)?.description ?? null;
}

export default function SocialProfileScreen() {
  const router = useRouter();
  const { activateSocial } = useLocalSearchParams<{ activateSocial?: string }>();
  const { width } = useWindowDimensions();
  const { confirmPayment: confirmStripePayment } = useStripe();
  const user = useUserStore((state) => state.user);
  const updateProfile = useUserStore((state) => state.updateProfile);
  const selectedBranch = useBranchStore((state) => state.seleccionada);
  const tableSession = useTableSessionStore((state) => state.session);

  const [form, setForm] = useState<SocialFormState>(EMPTY_FORM);
  const [selectedInterests, setSelectedInterests] = useState<string[]>([]);
  const [modalVisible, setModalVisible] = useState(false);
  const [mesaModalVisible, setMesaModalVisible] = useState(false);
  const [consentModalVisible, setConsentModalVisible] = useState(false);
  const [filtersVisible, setFiltersVisible] = useState(false);
  const [detailsVisible, setDetailsVisible] = useState(false);
  const [receivedLikesVisible, setReceivedLikesVisible] = useState(false);
  const [giftsVisible, setGiftsVisible] = useState(false);
  const [uploading, setUploading] = useState(false);
  const [saving, setSaving] = useState(false);
  const [statusUpdating, setStatusUpdating] = useState(false);
  const [profileLoading, setProfileLoading] = useState(true);
  const [dinersLoading, setDinersLoading] = useState(false);
  const [matchesLoading, setMatchesLoading] = useState(false);
  const [likingUserId, setLikingUserId] = useState<number | null>(null);
  const [giftProductsLoading, setGiftProductsLoading] = useState(false);
  const [giftSending, setGiftSending] = useState(false);
  const [giftCardComplete, setGiftCardComplete] = useState(false);
  const [giftPaymentSession, setGiftPaymentSession] = useState<{
    giftId: number;
    clientSecret: string;
    intentId: string;
    stripeConfirmed: boolean;
  } | null>(null);
  const [mesaOptionsLoading, setMesaOptionsLoading] = useState(false);
  const [modoSocial, setModoSocial] = useState(false);
  const [hasCompleteProfile, setHasCompleteProfile] = useState(false);
  const [requiresSocialConsent, setRequiresSocialConsent] = useState(false);
  const [socialConsentChecked, setSocialConsentChecked] = useState(false);
  const [socialPhotos, setSocialPhotos] = useState<string[]>([]);
  const [diners, setDiners] = useState<SocialDiner[]>([]);
  const [matches, setMatches] = useState<SocialDiner[]>([]);
  const [receivedLikes, setReceivedLikes] = useState<SocialDiner[]>([]);
  const [currentIndex, setCurrentIndex] = useState(0);
  const [detailPhotoIndex, setDetailPhotoIndex] = useState(0);
  const [dinerDetails, setDinerDetails] = useState<Record<number, SocialDiner>>({});
  const [focusedDiner, setFocusedDiner] = useState<SocialDiner | null>(null);
  const [giftProducts, setGiftProducts] = useState<GiftProduct[]>([]);
  const [selectedGiftId, setSelectedGiftId] = useState<number | null>(null);
  const [filters, setFilters] = useState<SocialFilterState>(EMPTY_FILTERS);
  const [mesaInput, setMesaInput] = useState('');
  const [mesaOptions, setMesaOptions] = useState<SelectOption[]>([]);
  const [pendingActivationAfterBranch, setPendingActivationAfterBranch] = useState(false);
  const [pendingSocialMesaValue, setPendingSocialMesaValue] = useState<string | null>(null);
  const [socialView, setSocialView] = useState<SocialView>('discover');
  const userSocialActive = Boolean(user?.is_social_active || user?.modo_social);

  const translateX = useSharedValue(0);
  const translateY = useSharedValue(0);
  const rotate = useSharedValue(0);
  const detailPhotoScrollRef = useRef<ScrollView | null>(null);
  const giftRequestKeyRef = useRef<string | null>(null);
  const socialScanActivationHandledRef = useRef(false);

  const interestOptions = useMemo(() => {
    const unique = new Set(DEFAULT_INTEREST_OPTIONS);
    selectedInterests.forEach((interest) => unique.add(interest));
    return Array.from(unique);
  }, [selectedInterests]);
  const activeFilterCount = useMemo(
    () => [filters.edadMin, filters.edadMax, filters.genero, filters.sexualidad].filter(Boolean).length,
    [filters]
  );

  const SWIPE_THRESHOLD = width * 0.35;

  const safeCurrentIndex = diners.length > 0 ? moduloIndex(currentIndex, diners.length) : 0;
  const currentDinerBase = diners[safeCurrentIndex] ?? null;
  const currentDinerDetail = currentDinerBase ? dinerDetails[currentDinerBase.user_id] : undefined;
  const currentDiner =
    currentDinerBase && currentDinerDetail
      ? {
          ...currentDinerBase,
          ...currentDinerDetail,
        }
      : currentDinerBase;
  const detailDiner = focusedDiner ?? currentDiner;
  const selectedGift = giftProducts.find((item) => item.id === selectedGiftId) ?? null;
  const detailPhotos = detailDiner
    ? normalizePhotoList(detailDiner.social_photos, detailDiner.foto_url)
    : [];
  const detailImageWidth = Math.max(260, width - 36);
  const topReceivedLike = receivedLikes[0] ?? null;
  const receivedLikeTitle = topReceivedLike
    ? receivedLikes.length > 1
      ? `${topReceivedLike.nombre} y ${receivedLikes.length - 1} mas te dieron me gusta`
      : `${topReceivedLike.nombre} te dio me gusta`
    : '';
  const receivedLikeSubtitle = receivedLikes.length > 1 ? 'Toca para ver todos los perfiles' : 'Toca para ver el perfil';

  function advanceToDiner(direction: 'next' | 'prev') {
    if (diners.length <= 1) {
      return;
    }

    const offset = direction === 'next' ? 1 : -1;
    setCurrentIndex((previous) => moduloIndex(previous + offset, diners.length));
  }

  function isLikedOrMatched(diner: SocialDiner): boolean {
    return diner.relationship_status === 'liked' || diner.relationship_status === 'matched';
  }

  function scrollDetailPhoto(direction: 'next' | 'prev') {
    if (detailPhotos.length <= 1) return;

    const offset = direction === 'next' ? 1 : -1;
    const nextIndex = moduloIndex(detailPhotoIndex + offset, detailPhotos.length);
    setDetailPhotoIndex(nextIndex);
    detailPhotoScrollRef.current?.scrollTo({ x: nextIndex * detailImageWidth, animated: true });
  }

  function handleDetailPhotoScroll(event: NativeSyntheticEvent<NativeScrollEvent>) {
    const x = event.nativeEvent.contentOffset.x;
    const nextIndex = Math.round(x / detailImageWidth);
    if (nextIndex !== detailPhotoIndex) {
      setDetailPhotoIndex(nextIndex);
    }
  }

  function updateDinerRelationship(
    userId: number,
    relationshipStatus: SocialDiner['relationship_status'],
    nextDiner?: SocialDiner
  ) {
    const applyStatus = (diner: SocialDiner): SocialDiner =>
      diner.user_id === userId
        ? {
            ...diner,
            ...nextDiner,
            relationship_status: relationshipStatus,
          }
        : diner;

    setDiners((current) => current.map(applyStatus));
    setDinerDetails((current) => {
      if (!current[userId]) {
        return current;
      }

      return {
        ...current,
        [userId]: applyStatus(current[userId]),
      };
    });
    setFocusedDiner((current) => (current?.user_id === userId ? applyStatus(current) : current));
  }

  const animatedCardStyle = useAnimatedStyle(() => {
    return {
      transform: [
        { translateX: translateX.value },
        { translateY: translateY.value },
        { rotate: `${rotate.value}deg` },
      ],
    };
  });

  const panGesture = Gesture.Pan()
    .onUpdate((event) => {
      translateX.value = event.translationX;
      translateY.value = event.translationY;
      rotate.value = (event.translationX / width) * 15;
    })
    .onEnd((event) => {
      if (Math.abs(event.translationX) > SWIPE_THRESHOLD) {
        const targetX = event.translationX > 0 ? width + 100 : -width - 100;
        translateX.value = withTiming(targetX, { duration: 250 });
        translateY.value = withTiming(event.translationY, { duration: 250 });
        rotate.value = withTiming(event.translationX > 0 ? 20 : -20, { duration: 250 }, () => {
          runOnJS(handleNextDiner)();
          translateX.value = 0;
          translateY.value = 0;
          rotate.value = 0;
        });
      } else {
        translateX.value = withSpring(0, { damping: 15, stiffness: 150 });
        translateY.value = withSpring(0, { damping: 15, stiffness: 150 });
        rotate.value = withSpring(0, { damping: 15, stiffness: 150 });
      }
    });

  useEffect(() => {
    setDetailPhotoIndex(0);
    detailPhotoScrollRef.current?.scrollTo({ x: 0, animated: false });
  }, [detailDiner?.user_id]);

  useEffect(() => {
    setModoSocial(userSocialActive);

    if (!userSocialActive) {
      setDiners([]);
      setCurrentIndex(0);
      setDinerDetails({});
      setConsentModalVisible(false);
      setPendingSocialMesaValue(null);
    }

    if (typeof user?.requires_social_consent === 'boolean') {
      setRequiresSocialConsent(user.requires_social_consent);
    }

    const nextMesa = normalizeMesaValue(user?.mesa);
    if (nextMesa !== null) {
      setMesaInput(nextMesa);
    }
  }, [userSocialActive, user?.requires_social_consent, user?.mesa]);

  useEffect(() => {
    if (activateSocial !== '1' || socialScanActivationHandledRef.current || !tableSession?.mesaValue || modoSocial) {
      return;
    }

    socialScanActivationHandledRef.current = true;
    void requestSocialActivation(tableSession.mesaValue);
  }, [activateSocial, tableSession?.mesaValue, modoSocial]);

  useEffect(() => {
    let mounted = true;

    async function loadProfile() {
      try {
        setProfileLoading(true);
        const response = await apiClient.get<ApiEnvelope<SocialProfileResponse> | SocialProfileResponse>(
          '/users/social-profile'
        );
        const data = unwrapApiData(response.data);
        if (!mounted) return;

        const redes = parseSocialNetworks(data.redes_sociales);
        const photos = normalizePhotoList(data.social_photos, data.foto_url ?? user?.foto_url ?? null);
        const photoUrl = photos[0] ?? resolvePhotoUrl(data.foto_url) ?? user?.foto_url ?? null;
        const socialEnabled = Boolean(data.is_social_active ?? data.modo_social ?? user?.is_social_active ?? user?.modo_social);
        const mesaValue = normalizeMesaValue(data.mesa) ?? '';

        setForm({
          nombre: data.nombre ?? user?.nombre ?? '',
          edad: data.edad?.toString() ?? '',
          genero: data.genero ?? '',
          sexualidad: data.sexualidad ?? '',
          queBusca: data.que_busca ?? '',
          biografia: data.descripcion ?? '',
          instagram: redes.instagram,
          tiktok: redes.tiktok,
        });
        setSelectedInterests(parseInterestList(data.intereses));
        setSocialPhotos(photos);
        setMesaInput(mesaValue);
        setModoSocial(socialEnabled);
        setRequiresSocialConsent(Boolean(data.requires_social_consent));
        setHasCompleteProfile(
          data.has_social_profile ??
            isProfileReady({
              foto_url: photoUrl,
              edad: data.edad,
              sexualidad: data.sexualidad,
              genero: data.genero,
              descripcion: data.descripcion,
            })
        );

        updateProfile({
          nombre: data.nombre ?? user?.nombre ?? '',
          foto_url: photoUrl,
          social_photos: photos,
          edad: data.edad,
          genero: data.genero,
          sexualidad: data.sexualidad,
          gustos: data.intereses,
          biografia: data.descripcion,
          que_busca: data.que_busca,
          redes_sociales: data.redes_sociales,
          instagram: redes.instagram || null,
          tiktok: redes.tiktok || null,
          is_social_active: socialEnabled,
          modo_social: socialEnabled,
          current_restaurante_id: data.current_restaurante_id ?? null,
          mesa: normalizeMesaValue(data.mesa),
          social_consent_accepted_at: data.social_consent_accepted_at ?? null,
          social_consent_version: data.social_consent_version ?? null,
          requires_social_consent: Boolean(data.requires_social_consent),
        });
      } catch (error) {
        if (mounted) {
          Alert.alert('Perfil social', getApiError(error));
        }
      } finally {
        if (mounted) {
          setProfileLoading(false);
        }
      }
    }

    loadProfile();

    return () => {
      mounted = false;
    };
  }, []);

  useEffect(() => {
    if (!pendingActivationAfterBranch || !selectedBranch?.id) {
      return;
    }

    const timer = setTimeout(() => {
      setPendingActivationAfterBranch(false);
      void openMesaPrompt();
    }, 220);

    return () => clearTimeout(timer);
  }, [pendingActivationAfterBranch, selectedBranch?.id]);

  useEffect(() => {
    let mounted = true;

    async function loadActiveDiners() {
      if (!modoSocial || !selectedBranch?.id) {
        setDiners([]);
        setCurrentIndex(0);
        return;
      }

      try {
        setDinersLoading(true);
        const requestParams: Record<string, string | number> = {};

        if (filters.edadMin) {
          requestParams.edad_min = Number(filters.edadMin);
        }
        if (filters.edadMax) {
          requestParams.edad_max = Number(filters.edadMax);
        }
        if (filters.genero) {
          requestParams.genero = filters.genero;
        }
        if (filters.sexualidad) {
          requestParams.sexualidad = filters.sexualidad;
        }

        let response;
        try {
          response = await apiClient.get<{ success?: boolean; data?: DinerApiItem[] }>(
            `/restaurants/${selectedBranch.id}/active-diners`,
            { params: requestParams }
          );
        } catch (error) {
          response = await apiClient.get<{ success?: boolean; data?: DinerApiItem[] }>(
            `/restaurants/${selectedBranch.id}/active-users`,
            { params: requestParams }
          );
        }

        if (!mounted) return;

        const rawItems = Array.isArray(response.data)
          ? response.data
          : Array.isArray(response.data?.data)
            ? response.data.data
            : [];

        const nextDiners = rawItems.map(buildDiner);
        setDiners(nextDiners);
        setCurrentIndex(0);
      } catch (error) {
        if (mounted) {
          setDiners([]);
          Alert.alert('Comensales', getApiError(error));
        }
      } finally {
        if (mounted) {
          setDinersLoading(false);
        }
      }
    }

    loadActiveDiners();

    return () => {
      mounted = false;
    };
  }, [filters, modoSocial, selectedBranch?.id]);

  useEffect(() => {
    let mounted = true;

    async function loadCurrentDinerDetails() {
      if (!modoSocial || !currentDiner || dinerDetails[currentDiner.user_id]) return;

      try {
        const response = await apiClient.get<{ success?: boolean; data?: DinerApiItem } | DinerApiItem>(
          `/users/${currentDiner.user_id}/public-profile`
        );
        if (!mounted) return;

        const rawProfile =
          response.data && typeof response.data === 'object' && 'data' in response.data
            ? response.data.data
            : response.data;

        if (!isDinerApiItem(rawProfile)) {
          return;
        }

        setDinerDetails((current) => ({
          ...current,
          [currentDiner.user_id]: buildDiner(rawProfile),
        }));
      } catch {
        // The base diner data is enough to keep the UI working.
      }
    }

    loadCurrentDinerDetails();

    return () => {
      mounted = false;
    };
  }, [currentDiner?.user_id, modoSocial]);

  async function refreshMatches(showLoading = false) {
    try {
      if (showLoading) {
        setMatchesLoading(true);
      }
      const response = await apiClient.get<ApiEnvelope<{ matches: DinerApiItem[] }> | { matches: DinerApiItem[] }>(
        '/social/matches',
        { _suppressConsoleError: true } as any
      );
      const data = unwrapApiData(response.data);
      const rawMatches = Array.isArray(data.matches) ? data.matches : [];
      setMatches(rawMatches.map(buildDiner));
    } catch {
      if (showLoading) {
        setMatches([]);
      }
    } finally {
      if (showLoading) {
        setMatchesLoading(false);
      }
    }
  }

  async function refreshReceivedLikes() {
    try {
      const response = await apiClient.get<ApiEnvelope<{ likes: DinerApiItem[] }> | { likes: DinerApiItem[] }>(
        '/social/likes/received',
        { _suppressConsoleError: true } as any
      );
      const data = unwrapApiData(response.data);
      const rawLikes = Array.isArray(data.likes) ? data.likes : [];
      setReceivedLikes(rawLikes.map(buildDiner));
    } catch {
      // Best effort: older API deployments simply won't show the notification card.
    }
  }

  useEffect(() => {
    if (!user?.id) {
      return undefined;
    }

    void refreshMatches(true);
    void refreshReceivedLikes();

    const refreshTimer = setInterval(() => {
      void refreshMatches(false);
      void refreshReceivedLikes();
    }, 7000);

    return () => clearInterval(refreshTimer);
  }, [user?.id]);

  useEffect(() => {
    if (receivedLikesVisible && receivedLikes.length === 0) {
      setReceivedLikesVisible(false);
    }
  }, [receivedLikes.length, receivedLikesVisible]);

  function updateField<K extends keyof SocialFormState>(field: K, value: SocialFormState[K]) {
    setForm((current) => ({ ...current, [field]: value }));
  }

  function updateFilter<K extends keyof SocialFilterState>(field: K, value: SocialFilterState[K]) {
    setFilters((current) => ({ ...current, [field]: value }));
  }

  function toggleInterest(interest: string) {
    setSelectedInterests((current) =>
      current.includes(interest) ? current.filter((item) => item !== interest) : [...current, interest]
    );
  }

  function handleDinerCardPress(diner?: SocialDiner | null) {
    setFocusedDiner(diner ?? null);
    setDetailsVisible(true);
  }

  function handleOpenReceivedLikes() {
    if (receivedLikes.length === 0) return;
    setReceivedLikesVisible(true);
  }

  function handleCloseReceivedLikes() {
    setReceivedLikesVisible(false);
  }

  function handleReceivedLikePress(diner: SocialDiner) {
    setReceivedLikesVisible(false);
    handleDinerCardPress(diner);
  }

  function handleCloseDinerDetails() {
    setDetailsVisible(false);
    setFocusedDiner(null);
  }

  function handleNextDiner() {
    advanceToDiner('next');
  }

  async function handleLikeDiner(userId: number) {
    if (likingUserId !== null) return;

    const diner = diners.find((item) => item.user_id === userId) ?? currentDiner;
    if (!diner) return;

    if (!selectedBranch?.id) {
      Alert.alert('Sucursal requerida', 'Selecciona una sucursal antes de dar like.');
      return;
    }

    try {
      setLikingUserId(userId);
      const response = await apiClient.post<ApiEnvelope<SocialLikeResponse> | SocialLikeResponse>('/social/likes', {
        liked_user_id: userId,
        restaurant_id: selectedBranch.id,
      });
      const result = unwrapApiData(response.data);
      const matchedDiner = result.match ? buildDiner(result.match) : { ...diner, relationship_status: result.relationship_status };

      updateDinerRelationship(userId, result.relationship_status, matchedDiner);
      setReceivedLikes((current) => current.filter((item) => item.user_id !== userId));

      if (result.matched) {
        setMatches((current) => {
          const withoutDuplicate = current.filter((item) => item.user_id !== matchedDiner.user_id);
          return [{ ...matchedDiner, relationship_status: 'matched' }, ...withoutDuplicate];
        });
        Alert.alert('¡Es match!', `${matchedDiner.nombre} tambien te dio like. Lo agregamos a Mis matches.`);
      }
      void refreshMatches(false);
      void refreshReceivedLikes();
    } catch (error) {
      Alert.alert('No se pudo enviar el like', getApiError(error));
    } finally {
      setLikingUserId(null);
    }
  }

  function applyPhotoResponse(payload: SocialPhotoResponse) {
    const hasGalleryPayload = Array.isArray(payload.social_photos);
    const photos = normalizePhotoList(
      hasGalleryPayload ? payload.social_photos : undefined,
      hasGalleryPayload ? payload.foto_url ?? null : payload.foto_url ?? user?.foto_url ?? null
    );
    const photoUrl = photos[0] ?? resolvePhotoUrl(payload.foto_url) ?? null;

    setSocialPhotos(photos);
    updateProfile({ foto_url: photoUrl, social_photos: photos });
    setHasCompleteProfile((current) =>
      isProfileReady({
        foto_url: photoUrl,
        edad: Number(form.edad) || null,
        sexualidad: form.sexualidad,
        genero: form.genero,
        descripcion: form.biografia,
      }) || current
    );
  }

  async function handlePickImage() {
    if (socialPhotos.length >= 6) {
      Alert.alert('Galeria completa', 'Puedes tener hasta 6 fotos en tu perfil social.');
      return;
    }

    Alert.alert('Agregar foto', 'Elige de donde tomar la imagen.', [
      { text: 'Camara', onPress: () => openPicker(true) },
      { text: 'Galeria', onPress: () => openPicker(false) },
      { text: 'Cancelar', style: 'cancel' },
    ]);
  }

  async function openPicker(isCamera: boolean) {
    const remainingSlots = Math.max(0, 6 - socialPhotos.length);
    if (remainingSlots <= 0) {
      Alert.alert('Galeria completa', 'Puedes tener hasta 6 fotos en tu perfil social.');
      return;
    }

    const permissionResult = isCamera
      ? await ImagePicker.requestCameraPermissionsAsync()
      : await ImagePicker.requestMediaLibraryPermissionsAsync();

    if (!permissionResult.granted) {
      Alert.alert('Permiso requerido', 'Necesitamos acceso para cambiar tu foto.');
      return;
    }

    const result = isCamera
      ? await ImagePicker.launchCameraAsync({ allowsEditing: true, aspect: [1, 1], quality: 0.7 })
      : await ImagePicker.launchImageLibraryAsync({
          allowsEditing: remainingSlots === 1,
          allowsMultipleSelection: remainingSlots > 1,
          selectionLimit: remainingSlots,
          orderedSelection: true,
          aspect: [1, 1],
          quality: 0.7,
        });

    if (result.canceled) {
      return;
    }

    const selectedUris = result.assets
      .map((asset) => asset.uri)
      .filter((uri): uri is string => Boolean(uri))
      .slice(0, remainingSlots);

    if (selectedUris.length > 0) {
      await uploadAvatars(selectedUris);
    }
  }

  async function uploadSingleSocialPhoto(uri: string): Promise<SocialPhotoResponse> {
    const formData = new FormData();
    const filename = uri.split('/').pop() || 'avatar.jpg';
    const extension = /\.(\w+)$/.exec(filename)?.[1]?.toLowerCase() ?? 'jpg';
    const type = extension === 'jpg' ? 'image/jpeg' : `image/${extension}`;

    formData.append('photo', { uri, name: filename, type } as never);

    const response = await apiClient.post<ApiEnvelope<SocialPhotoResponse> | SocialPhotoResponse>('/users/social-profile/photo', formData, {
      headers: { 'Content-Type': 'multipart/form-data' },
      timeout: 60000,
    });

    return unwrapApiData(response.data);
  }

  async function uploadAvatars(uris: string[]) {
    setUploading(true);

    try {
      for (const uri of uris) {
        const payload = await uploadSingleSocialPhoto(uri);
        applyPhotoResponse(payload);
      }

      Alert.alert(
        'Listo',
        uris.length === 1
          ? 'La foto se agrego a tu perfil social.'
          : `${uris.length} fotos se agregaron a tu perfil social.`
      );
    } catch (error) {
      Alert.alert('No se pudo subir la foto', getApiError(error));
    } finally {
      setUploading(false);
    }
  }

  async function handleSetPrimaryPhoto(photoUrl: string) {
    try {
      setUploading(true);
      const response = await apiClient.post<ApiEnvelope<SocialPhotoResponse> | SocialPhotoResponse>(
        '/users/social-profile/photo/primary',
        { photo_url: photoUrl }
      );

      applyPhotoResponse(unwrapApiData(response.data));
    } catch (error) {
      Alert.alert('No se pudo cambiar la principal', getApiError(error));
    } finally {
      setUploading(false);
    }
  }

  async function handleDeletePhoto(photoUrl: string) {
    Alert.alert('Eliminar foto', 'Esta foto se quitara de tu perfil social.', [
      { text: 'Cancelar', style: 'cancel' },
      {
        text: 'Eliminar',
        style: 'destructive',
        onPress: async () => {
          try {
            setUploading(true);
            const response = await apiClient.delete<ApiEnvelope<SocialPhotoResponse> | SocialPhotoResponse>(
              '/users/social-profile/photo',
              { data: { photo_url: photoUrl } }
            );

            applyPhotoResponse(unwrapApiData(response.data));
          } catch (error) {
            Alert.alert('No se pudo eliminar', getApiError(error));
          } finally {
            setUploading(false);
          }
        },
      },
    ]);
  }

  async function requestSocialActivation(mesaValue: string) {
    if (requiresSocialConsent) {
      setPendingSocialMesaValue(mesaValue);
      setSocialConsentChecked(false);
      setConsentModalVisible(true);
      return;
    }

    await persistSocialStatus(true, mesaValue);
  }

  async function handleAcceptSocialConsent() {
    if (!socialConsentChecked) {
      Alert.alert('Confirmacion requerida', 'Marca la casilla para confirmar que aceptas compartir tus datos sociales.');
      return;
    }

    if (!pendingSocialMesaValue) {
      setConsentModalVisible(false);
      return;
    }

    const mesaValue = pendingSocialMesaValue;
    setConsentModalVisible(false);
    setPendingSocialMesaValue(null);
    await persistSocialStatus(true, mesaValue, true);
  }

  function handleCancelSocialConsent() {
    setConsentModalVisible(false);
    setSocialConsentChecked(false);
    setPendingSocialMesaValue(null);
  }

  async function openPrivacyNotice() {
    try {
      await Linking.openURL(SOCIAL_PRIVACY_NOTICE_URL);
    } catch {
      Alert.alert('Aviso de privacidad', SOCIAL_PRIVACY_NOTICE_URL);
    }
  }

  async function updateSocialStatus(nextValue: boolean) {
    if (nextValue && !selectedBranch?.id) {
      Alert.alert(
        'Sucursal requerida',
        'Selecciona una sucursal antes de activar el modo social.',
        [
          {
            text: 'Elegir sucursal',
            onPress: () => {
              setPendingActivationAfterBranch(true);
              router.push('/branch-selector' as never);
            },
          },
          {
            text: 'Cancelar',
            style: 'cancel',
            onPress: () => setPendingActivationAfterBranch(false),
          },
        ]
      );
      return;
    }

    if (nextValue && !hasCompleteProfile) {
      Alert.alert(
        'Completa tu perfil',
        'Necesitas foto, edad, genero, sexualidad y descripcion para aparecer a otros comensales.',
        [{ text: 'Editar perfil', onPress: () => setModalVisible(true) }, { text: 'Cerrar', style: 'cancel' }]
      );
      return;
    }

    if (nextValue) {
      if (tableSession?.mesaValue) {
        await requestSocialActivation(tableSession.mesaValue);
        return;
      }

      Alert.alert('Escanea tu mesa', 'Para activar el modo social primero escanea el QR de tu mesa.', [
        { text: 'Cancelar', style: 'cancel' },
        {
          text: 'Escanear QR',
          onPress: () =>
            router.push({ pathname: '/table-scanner', params: { returnTo: '/profile/social', activateSocial: '1' } }),
        },
      ]);
      return;
    }

    await persistSocialStatus(false, null);
  }

  async function openMesaPrompt() {
    router.push({ pathname: '/table-scanner', params: { returnTo: '/profile/social', activateSocial: '1' } });
  }

  async function persistSocialStatus(nextValue: boolean, mesaValue: string | null, acceptsSocialPrivacy = false) {
    try {
      setStatusUpdating(true);
      const socialRestaurantId = tableSession?.restauranteId ?? selectedBranch?.id ?? null;
      const response = await apiClient.post<ApiEnvelope<SocialStatusResponse> | SocialStatusResponse>('/users/social-status', {
        is_social_active: nextValue,
        current_restaurante_id: nextValue ? socialRestaurantId : null,
        mesa: nextValue ? mesaValue : null,
        accepts_social_privacy: nextValue ? acceptsSocialPrivacy : false,
      }, {
        _suppressConsoleError: nextValue,
      } as never);
      const result = unwrapApiData(response.data);

      if (!nextValue) {
        setDiners([]);
        setCurrentIndex(0);
        setDinerDetails({});
      }

      setMesaInput(nextValue ? mesaValue ?? '' : '');
      setMesaModalVisible(false);
      setPendingActivationAfterBranch(false);
      setModoSocial(nextValue);
      setRequiresSocialConsent(Boolean(result.requires_social_consent));
      updateProfile({
        is_social_active: nextValue,
        modo_social: nextValue,
        current_restaurante_id: nextValue ? socialRestaurantId : null,
        mesa: nextValue ? normalizeMesaValue(mesaValue) : null,
        social_consent_accepted_at: result.social_consent_accepted_at ?? user?.social_consent_accepted_at ?? null,
        social_consent_version: result.social_consent_version ?? user?.social_consent_version ?? null,
        requires_social_consent: Boolean(result.requires_social_consent),
      });
    } catch (error) {
      const message = getApiError(error);
      const normalizedMessage = message.toLowerCase();

      if (normalizedMessage.includes('debes completar tu perfil social')) {
        setHasCompleteProfile(false);
        setModoSocial(false);
        setMesaModalVisible(false);
        setPendingActivationAfterBranch(false);
        setModalVisible(true);
        return;
      }

      if (nextValue && normalizedMessage.includes('aviso de privacidad')) {
        setRequiresSocialConsent(true);
        if (mesaValue) {
          setPendingSocialMesaValue(mesaValue);
          setSocialConsentChecked(false);
          setConsentModalVisible(true);
        }
        return;
      }

      Alert.alert('Modo social', message);
    } finally {
      setStatusUpdating(false);
    }
  }

  async function handleConfirmMesa() {
    const cleanMesa = mesaInput.trim();

    if (!cleanMesa) {
      Alert.alert('Mesa requerida', 'Ingresa tu numero de mesa para activar el modo social.');
      return;
    }

    await persistSocialStatus(true, cleanMesa);
  }

  async function handleSaveProfile() {
    const edad = Number(form.edad);

    if (!Number.isFinite(edad) || edad <= 0) {
      Alert.alert('Edad invalida', 'Ingresa una edad valida.');
      return;
    }

    if (!form.nombre.trim()) {
      Alert.alert('Nombre requerido', 'Ingresa el nombre que quieres mostrar en tu perfil social.');
      return;
    }

    if (!form.genero || !form.sexualidad || !form.biografia.trim()) {
      Alert.alert('Perfil incompleto', 'Genero, sexualidad y descripcion son obligatorios.');
      return;
    }

    try {
      setSaving(true);

      const payload = {
        nombre: form.nombre.trim(),
        edad,
        genero: form.genero,
        sexualidad: form.sexualidad,
        descripcion: form.biografia.trim(),
        intereses: selectedInterests.length > 0 ? selectedInterests.join(', ') : null,
        que_busca: form.queBusca || null,
        redes_sociales: stringifySocialNetworks(form.instagram, form.tiktok),
      };

      const response = await apiClient.put<ApiEnvelope<SocialProfileResponse> | SocialProfileResponse>(
        '/users/social-profile',
        payload
      );
      const data = unwrapApiData(response.data);
      const redes = parseSocialNetworks(data.redes_sociales);
      const photos = normalizePhotoList(data.social_photos, data.foto_url ?? user?.foto_url ?? null);
      const photoUrl = photos[0] ?? resolvePhotoUrl(data.foto_url) ?? user?.foto_url ?? null;
      const ready =
        data.has_social_profile ??
        isProfileReady({
          foto_url: photoUrl,
          edad: data.edad,
          sexualidad: data.sexualidad,
          genero: data.genero,
          descripcion: data.descripcion,
        });

      setHasCompleteProfile(ready);
      setSocialPhotos(photos);
      setModalVisible(false);

      updateProfile({
        nombre: data.nombre,
        foto_url: photoUrl,
        social_photos: photos,
        edad: data.edad,
        genero: data.genero,
        sexualidad: data.sexualidad,
        gustos: data.intereses,
        biografia: data.descripcion,
        que_busca: data.que_busca,
        redes_sociales: data.redes_sociales,
        instagram: redes.instagram || null,
        tiktok: redes.tiktok || null,
      });

      Alert.alert('Perfil guardado', 'Tu perfil social quedo actualizado.');
    } catch (error) {
      Alert.alert('No se pudo guardar', getApiError(error));
    } finally {
      setSaving(false);
    }
  }

  async function openGiftSelector() {
    if (!detailDiner) return;

    if (detailsVisible) {
      setDetailsVisible(false);
      await new Promise((resolve) => setTimeout(resolve, Platform.OS === 'ios' ? 320 : 160));
    }

    try {
      setGiftsVisible(true);

      if (giftProducts.length > 0) {
        return;
      }

      setGiftProductsLoading(true);
      const response = await apiClient.get<{ success?: boolean; data?: GiftProduct[] } | GiftProduct[]>('/gift-products');
      const rawItems = Array.isArray(response.data)
        ? response.data
        : Array.isArray(response.data?.data)
          ? response.data.data
          : [];

      const normalized = rawItems.map(normalizeGiftProduct).filter((item) => item.es_regalo !== false);
      setGiftProducts(normalized);
      setSelectedGiftId((current) => current ?? normalized[0]?.id ?? null);
    } catch (error) {
      Alert.alert('Regalos', getApiError(error));
      closeGiftSelector();
    } finally {
      setGiftProductsLoading(false);
    }
  }

  function closeGiftSelector() {
    if (giftSending) return;
    setGiftsVisible(false);
    setFocusedDiner(null);
    setGiftPaymentSession(null);
    setGiftCardComplete(false);
    giftRequestKeyRef.current = null;
  }

  async function handleSendGift() {
    if (!detailDiner || !selectedGift) {
      Alert.alert('Regalos', 'Selecciona un regalo para continuar.');
      return;
    }

    if (!selectedBranch?.id) {
      Alert.alert('Regalos', 'Selecciona una sucursal antes de enviar un regalo.');
      return;
    }

    try {
      setGiftSending(true);
      let session = giftPaymentSession;
      if (!session) {
        giftRequestKeyRef.current ??= `gift_${Date.now()}_${Math.random().toString(36).slice(2, 14)}`;
        const response = await apiClient.post<ApiEnvelope<{
          gift: { id: number };
          client_secret: string;
          payment_intent_id: string;
        }>>('/social-gifts', {
          restaurant_id: selectedBranch.id,
          recipient_user_id: detailDiner.user_id,
          gift_product_id: selectedGift.id,
          request_key: giftRequestKeyRef.current,
        });
        const prepared = unwrapApiData(response.data);
        if (!prepared?.gift?.id || !prepared.client_secret || !prepared.payment_intent_id) {
          throw new Error('No se pudo preparar el pago del regalo.');
        }
        session = {
          giftId: prepared.gift.id,
          clientSecret: prepared.client_secret,
          intentId: prepared.payment_intent_id,
          stripeConfirmed: false,
        };
        setGiftPaymentSession(session);
      }

      if (!session.stripeConfirmed) {
        if (!giftCardComplete) throw new Error('Completa los datos de la tarjeta.');
        const { error } = await confirmStripePayment(session.clientSecret, { paymentMethodType: 'Card' });
        if (error) {
          Alert.alert('Pago rechazado', error.message);
          return;
        }
        session = { ...session, stripeConfirmed: true };
        setGiftPaymentSession(session);
      }

      const confirmation = await apiClient.post<ApiEnvelope<{
        id: number;
        folio?: string;
        mesa_label?: string;
        gift_nombre: string;
        recipient_nombre: string;
      }>>(`/social-gifts/${session.giftId}/confirm-payment`, {
        payment_intent_id: session.intentId,
      });
      const result = unwrapApiData(confirmation.data);
      const giftName = result?.gift_nombre ?? selectedGift.nombre;
      const recipientName = result?.recipient_nombre ?? detailDiner.nombre;
      const mesaLabel = result?.mesa_label ?? (detailDiner.mesa ? `Mesa ${detailDiner.mesa}` : 'la mesa del comensal');
      const folio = result?.folio;

      setGiftsVisible(false);
      setFocusedDiner(null);
      setGiftPaymentSession(null);
      setGiftCardComplete(false);
      giftRequestKeyRef.current = null;
      Alert.alert(
        'Pago confirmado y regalo enviado',
        `Avisamos al equipo de meseros para entregar "${giftName}" a ${recipientName} en ${mesaLabel}${folio ? `.\nFolio: ${folio}` : '.'}`
      );
    } catch (error) {
      Alert.alert('No se pudo enviar', getApiError(error));
    } finally {
      setGiftSending(false);
    }
  }

  function showSexualityInfo(value?: string | null) {
    if (!value) return;
    const description = getSexualityDescription(value);
    Alert.alert(value, description || 'Esta persona eligio compartir esta informacion en su perfil.');
  }

  function renderSexualityChip(value?: string | null) {
    if (!value) return null;
    const hasDescription = Boolean(getSexualityDescription(value));

    return (
      <TouchableOpacity
        style={[styles.metaChip, styles.metaChipInteractive]}
        activeOpacity={0.75}
        onPress={() => showSexualityInfo(value)}
        accessibilityRole="button"
        accessibilityLabel={`Informacion sobre ${value}`}
      >
        <Text style={styles.metaChipText}>{value}</Text>
        {hasDescription ? <Ionicons name="information-circle-outline" size={15} color={Colors.primary} /> : null}
      </TouchableOpacity>
    );
  }

  function renderSwipeCard(
    diner: SocialDiner,
    options?: {
      onPress?: () => void;
      hintText?: string;
    }
  ) {
    const cardBody = (
      <>
        <TouchableOpacity
          activeOpacity={0.92}
          onPress={() => {
            options?.onPress?.();
          }}
          style={styles.dinerImageWrap}
        >
          {diner.foto_url ? (
            <Image source={{ uri: diner.foto_url }} style={styles.dinerImage} contentFit="cover" cachePolicy="disk" />
          ) : (
            <LinearGradient colors={[Colors.primary, Colors.primaryLight]} style={styles.dinerImageFallback}>
              <Text style={styles.dinerImageFallbackLetter}>{diner.nombre[0]?.toUpperCase() ?? '?'}</Text>
            </LinearGradient>
          )}

          {isLikedOrMatched(diner) ? (
            <View style={styles.likedBadge}>
              <Ionicons name="heart" size={16} color={Colors.white} />
              <Text style={styles.likedBadgeText}>{diner.relationship_status === 'matched' ? 'Match' : 'Like enviado'}</Text>
            </View>
          ) : null}

          <LinearGradient colors={['transparent', 'rgba(26,26,46,0.72)']} style={styles.dinerImageOverlay}>
            <Text style={styles.dinerImageName}>{diner.nombre}</Text>
            <Text style={styles.dinerImageHint}>
              {options?.hintText ?? 'Toca la foto para ver su perfil completo'}
            </Text>
          </LinearGradient>
        </TouchableOpacity>

        <View style={styles.dinerPreview}>
          {diner.que_busca ? <Text style={styles.previewEyebrow}>{diner.que_busca}</Text> : null}

          <View style={styles.previewMetaWrap}>
            {diner.mesa ? (
              <View style={styles.metaChip}>
                <Text style={styles.metaChipText}>Mesa {diner.mesa}</Text>
              </View>
            ) : null}
            {diner.edad ? (
              <View style={styles.metaChip}>
                <Text style={styles.metaChipText}>{diner.edad} años</Text>
              </View>
            ) : null}
            {diner.genero ? (
              <View style={styles.metaChip}>
                <Text style={styles.metaChipText}>{diner.genero}</Text>
              </View>
            ) : null}
            {renderSexualityChip(diner.sexualidad)}
          </View>

          <Text style={styles.previewText} numberOfLines={3}>
            {diner.descripcion?.trim()
              ? diner.descripcion
              : 'Desliza para conocer mas comensales y toca la foto para abrir su perfil completo.'}
          </Text>

        </View>
      </>
    );

    return <View style={styles.dinerCard}>{cardBody}</View>;
  }

  function renderDinerDetailsContent(diner: SocialDiner) {
    const networks = parseSocialNetworks(diner.redes_sociales);
    const primaryHandle = formatHandleLabel(networks.instagram || networks.tiktok);

    return (
      <View style={styles.dinerContent}>
        <Text style={styles.dinerName}>{diner.nombre}</Text>
        {diner.que_busca ? <Text style={styles.dinerSubtitle}>{diner.que_busca}</Text> : null}

        <View style={styles.metaWrap}>
          {diner.mesa ? (
            <View style={styles.metaChip}>
              <Text style={styles.metaChipText}>Mesa {diner.mesa}</Text>
            </View>
          ) : null}
          {diner.edad ? (
            <View style={styles.metaChip}>
              <Text style={styles.metaChipText}>{diner.edad} años</Text>
            </View>
          ) : null}
          {diner.genero ? (
            <View style={styles.metaChip}>
              <Text style={styles.metaChipText}>{diner.genero}</Text>
            </View>
          ) : null}
          {renderSexualityChip(diner.sexualidad)}
        </View>

        {diner.descripcion ? <Text style={styles.descriptionText}>{diner.descripcion}</Text> : null}

        {primaryHandle ? (
          <View style={styles.infoSection}>
            <Text style={styles.infoLabel}>Redes sociales</Text>
            <Text style={styles.infoValue}>{primaryHandle}</Text>
          </View>
        ) : null}

        {diner.intereses.length > 0 ? (
          <View style={styles.infoSection}>
            <Text style={styles.infoLabel}>Intereses</Text>
            <View style={styles.interestWrap}>
              {diner.intereses.map((interest) => (
                <View key={interest} style={styles.interestChip}>
                  <Text style={styles.interestChipText}>{interest}</Text>
                </View>
              ))}
            </View>
          </View>
        ) : null}

        {diner.relationship_status !== 'matched' ? (
          <TouchableOpacity
            activeOpacity={0.88}
            onPress={() => handleLikeDiner(diner.user_id)}
            style={[styles.likeDetailButton, likingUserId === diner.user_id && styles.saveButtonDisabled]}
            disabled={likingUserId === diner.user_id || diner.relationship_status === 'liked'}
          >
            {likingUserId === diner.user_id ? (
              <ActivityIndicator size="small" color={Colors.white} />
            ) : (
              <Ionicons name={diner.relationship_status === 'liked' ? 'heart' : 'heart-outline'} size={20} color={Colors.white} />
            )}
            <Text style={styles.giftButtonText}>
              {diner.relationship_status === 'liked' ? 'Like enviado' : 'Me gusta'}
            </Text>
          </TouchableOpacity>
        ) : null}

        <TouchableOpacity activeOpacity={0.88} onPress={openGiftSelector} style={styles.giftButton}>
          <Ionicons name="gift-outline" size={20} color={Colors.white} />
          <Text style={styles.giftButtonText}>Enviar regalo</Text>
        </TouchableOpacity>
      </View>
    );
  }

  function renderCurrentDiner() {
    if (dinersLoading) {
      return (
        <View style={styles.centerStateCard}>
          <ActivityIndicator size="large" color={Colors.primary} />
          <Text style={styles.centerStateTitle}>Buscando comensales</Text>
          <Text style={styles.centerStateText}>Estamos cargando a las personas activas en esta sucursal.</Text>
        </View>
      );
    }

    if (!selectedBranch?.id) {
      return (
        <View style={styles.centerStateCard}>
          <Ionicons name="location-outline" size={54} color={Colors.textMuted} />
          <Text style={styles.centerStateTitle}>Selecciona una sucursal</Text>
          <Text style={styles.centerStateText}>
            El modo social usa la sucursal actual para mostrar solo comensales cercanos a ti.
          </Text>
          <TouchableOpacity style={styles.primaryButton} onPress={() => router.push('/branch-selector' as never)}>
            <Ionicons name="navigate" size={18} color={Colors.white} />
            <Text style={styles.primaryButtonText}>Elegir sucursal</Text>
          </TouchableOpacity>
        </View>
      );
    }

    if (!currentDiner) {
      return (
        <View style={styles.centerStateCard}>
          <Ionicons name="people-outline" size={58} color={Colors.textMuted} />
          <Text style={styles.centerStateTitle}>Aun no hay comensales visibles</Text>
          <Text style={styles.centerStateText}>
            Cuando mas personas activen su modo social en {selectedBranch.nombre}, apareceran aqui.
          </Text>
          <TouchableOpacity style={styles.secondaryButton} onPress={() => setModalVisible(true)}>
            <Ionicons name="create-outline" size={18} color={Colors.primary} />
            <Text style={styles.secondaryButtonText}>Editar mi perfil social</Text>
          </TouchableOpacity>
        </View>
      );
    }

    const currentDinerHasRelationship = currentDiner.relationship_status !== 'none';
    const currentDinerLikeLabel =
      currentDiner.relationship_status === 'matched'
        ? 'Match'
        : currentDiner.relationship_status === 'liked'
          ? 'Like enviado'
          : 'Me gusta';

    return (
      <View style={styles.carouselArea}>
        <Text style={styles.counterText}>
          {safeCurrentIndex + 1} / {diners.length}
        </Text>

        <View style={styles.tinderCardContainer}>
          {currentDiner && (
            <GestureDetector gesture={panGesture}>
              <Animated.View style={[styles.tinderCard, animatedCardStyle]}>
                {renderSwipeCard(currentDiner, {
                  onPress: () => handleDinerCardPress(),
                })}
              </Animated.View>
            </GestureDetector>
          )}
        </View>

        <View style={styles.discoveryActions}>
          <TouchableOpacity
            style={[styles.discoveryArrowButton, diners.length <= 1 && styles.discoveryArrowButtonDisabled]}
            activeOpacity={0.86}
            onPress={() => advanceToDiner('prev')}
            disabled={diners.length <= 1}
            accessibilityRole="button"
            accessibilityLabel="Perfil anterior"
          >
            <Ionicons name="chevron-back" size={26} color={diners.length <= 1 ? Colors.textMuted : Colors.text} />
          </TouchableOpacity>

          <TouchableOpacity
            style={[
              styles.likeButton,
              (likingUserId === currentDiner?.user_id || currentDinerHasRelationship) && styles.saveButtonDisabled,
            ]}
            activeOpacity={0.86}
            onPress={() => currentDiner && handleLikeDiner(currentDiner.user_id)}
            disabled={!currentDiner || likingUserId === currentDiner.user_id || currentDinerHasRelationship}
          >
            {likingUserId === currentDiner?.user_id ? (
              <ActivityIndicator size="small" color={Colors.white} />
            ) : (
              <Ionicons name="heart" size={24} color={Colors.white} />
            )}
            <Text style={styles.likeButtonText}>{currentDinerLikeLabel}</Text>
          </TouchableOpacity>

          <TouchableOpacity
            style={[styles.discoveryArrowButton, diners.length <= 1 && styles.discoveryArrowButtonDisabled]}
            activeOpacity={0.86}
            onPress={handleNextDiner}
            disabled={diners.length <= 1}
            accessibilityRole="button"
            accessibilityLabel="Siguiente perfil"
          >
            <Ionicons name="chevron-forward" size={26} color={diners.length <= 1 ? Colors.textMuted : Colors.text} />
          </TouchableOpacity>
        </View>
      </View>
    );
  }

  function renderMatchesView() {
    if (matchesLoading) {
      return (
        <View style={styles.centerStateCard}>
          <ActivityIndicator size="large" color={Colors.primary} />
          <Text style={styles.centerStateTitle}>Cargando tus matches</Text>
          <Text style={styles.centerStateText}>Estamos buscando las personas que tambien te dieron like.</Text>
        </View>
      );
    }

    if (matches.length === 0) {
      return (
        <View style={styles.centerStateCard}>
          <Ionicons name="heart-outline" size={70} color={Colors.textMuted} />
          <Text style={styles.centerStateTitle}>Aun no tienes matches</Text>
          <Text style={styles.centerStateText}>
            Da like a personas que te interesen. Si tambien te dan like, apareceran aqui.
          </Text>
          <TouchableOpacity style={styles.primaryButton} onPress={() => setSocialView('discover')} activeOpacity={0.85}>
            <Ionicons name="people-outline" size={18} color={Colors.white} />
            <Text style={styles.primaryButtonText}>Descubrir comensales</Text>
          </TouchableOpacity>
        </View>
      );
    }

    return (
      <ScrollView contentContainerStyle={styles.matchesList} showsVerticalScrollIndicator={false}>
        {matches.map((match) => (
          <TouchableOpacity
            key={match.user_id}
            activeOpacity={0.88}
            style={styles.matchCard}
            onPress={() => handleDinerCardPress(match)}
          >
            {match.foto_url ? (
              <Image source={{ uri: match.foto_url }} style={styles.matchImage} contentFit="cover" cachePolicy="disk" />
            ) : (
              <LinearGradient colors={[Colors.primary, Colors.primaryLight]} style={styles.matchFallback}>
                <Text style={styles.matchFallbackLetter}>{match.nombre[0]?.toUpperCase() ?? '?'}</Text>
              </LinearGradient>
            )}

            <View style={styles.matchInfo}>
              <View style={styles.matchTitleRow}>
                <Text numberOfLines={1} style={styles.matchName}>{match.nombre}</Text>
                <Ionicons name="heart" size={18} color="#E11D48" />
              </View>
              <Text numberOfLines={1} style={styles.matchMeta}>
                {match.mesa ? `Mesa ${match.mesa}` : 'Match social'}
              </Text>
              {match.que_busca ? <Text numberOfLines={1} style={styles.matchIntent}>{match.que_busca}</Text> : null}
              {formatMatchDate(match.matched_at) ? (
                <Text numberOfLines={1} style={styles.matchDate}>Match desde {formatMatchDate(match.matched_at)}</Text>
              ) : null}
            </View>

            <Ionicons name="chevron-forward" size={20} color={Colors.textMuted} />
          </TouchableOpacity>
        ))}
      </ScrollView>
    );
  }

  return (
    <SafeAreaView style={styles.safe}>
      <Stack.Screen options={{ headerShown: false }} />

      <LinearGradient colors={['#F7F7FB', '#EEF1F7']} style={styles.heroBackground}>
        <View style={styles.header}>
          <TouchableOpacity style={styles.iconButton} onPress={() => router.back()} activeOpacity={0.8}>
            <Ionicons name="arrow-back" size={22} color={Colors.text} />
          </TouchableOpacity>

          <View style={styles.headerTextWrap}>
            <Text style={styles.headerTitle}>Comensales</Text>
            <Text style={styles.headerSubtitle}>
              {selectedBranch?.nombre ? `En ${selectedBranch.nombre}` : 'Activa tu modo social para descubrir gente'}
            </Text>
          </View>

          <TouchableOpacity style={styles.iconButton} onPress={() => setModalVisible(true)} activeOpacity={0.8}>
            <Ionicons name="settings-outline" size={22} color={Colors.text} />
          </TouchableOpacity>
        </View>

        <View style={styles.statusCard}>
          <View style={styles.statusTextBlock}>
            <View style={[styles.statusDot, { backgroundColor: modoSocial ? '#21A453' : '#D0D5DD' }]} />
            <View style={styles.statusBody}>
              <Text style={styles.statusTitle}>Modo social {modoSocial ? 'encendido' : 'apagado'}</Text>
              {modoSocial ? (
                <Text style={styles.statusHint}>Tu perfil sera visible para comensales de esta sucursal mientras este activo.</Text>
              ) : null}
            </View>
          </View>

          <Switch
            value={modoSocial}
            onValueChange={updateSocialStatus}
            disabled={statusUpdating}
            trackColor={{ false: '#D6DAE5', true: '#89A0E8' }}
            thumbColor={modoSocial ? Colors.primary : Colors.white}
            ios_backgroundColor="#D6DAE5"
          />
        </View>

        <View style={styles.summaryRow}>
          <TouchableOpacity style={styles.summaryPill} activeOpacity={0.8} onPress={() => setModalVisible(true)}>
            <Ionicons name="person-circle-outline" size={18} color={Colors.primary} />
            <Text numberOfLines={1} style={styles.summaryPillText}>
              {hasCompleteProfile ? 'Mi perfil' : 'Completar'}
            </Text>
          </TouchableOpacity>

          <TouchableOpacity
            style={styles.summaryPill}
            activeOpacity={0.8}
            onPress={() => {
              setPendingActivationAfterBranch(false);
              router.push('/branch-selector' as never);
            }}
          >
            <Ionicons name="location-outline" size={18} color={Colors.primary} />
            <Text numberOfLines={1} style={styles.summaryPillText}>
              {selectedBranch?.nombre ?? 'Sucursal'}
            </Text>
          </TouchableOpacity>

          <TouchableOpacity style={styles.summaryPill} activeOpacity={0.8} onPress={() => setFiltersVisible(true)}>
            <Ionicons name="options-outline" size={18} color={Colors.primary} />
            <Text numberOfLines={1} style={styles.summaryPillText}>
              {activeFilterCount > 0 ? `Filtros (${activeFilterCount})` : 'Filtros'}
            </Text>
          </TouchableOpacity>
        </View>

        <View style={styles.socialViewSwitch}>
          <TouchableOpacity
            activeOpacity={0.82}
            style={[styles.socialViewButton, socialView === 'discover' && styles.socialViewButtonActive]}
            onPress={() => setSocialView('discover')}
          >
            <Ionicons
              name="people-outline"
              size={17}
              color={socialView === 'discover' ? Colors.white : Colors.primary}
            />
            <Text style={[styles.socialViewText, socialView === 'discover' && styles.socialViewTextActive]}>
              Descubrir
            </Text>
          </TouchableOpacity>

          <TouchableOpacity
            activeOpacity={0.82}
            style={[styles.socialViewButton, socialView === 'matches' && styles.socialViewButtonActive]}
            onPress={() => setSocialView('matches')}
          >
            <Ionicons
              name="heart-outline"
              size={17}
              color={socialView === 'matches' ? Colors.white : Colors.primary}
            />
            <Text style={[styles.socialViewText, socialView === 'matches' && styles.socialViewTextActive]}>
              Mis matches {matches.length > 0 ? `(${matches.length})` : ''}
            </Text>
          </TouchableOpacity>
        </View>

        {topReceivedLike ? (
          <TouchableOpacity
            style={styles.incomingLikeCard}
            activeOpacity={0.88}
            onPress={handleOpenReceivedLikes}
          >
            {topReceivedLike.foto_url ? (
              <Image source={{ uri: topReceivedLike.foto_url }} style={styles.incomingLikeAvatar} contentFit="cover" />
            ) : (
              <LinearGradient colors={['#EC4899', '#8B5CF6']} style={styles.incomingLikeAvatarFallback}>
                <Text style={styles.incomingLikeAvatarLetter}>{topReceivedLike.nombre.charAt(0).toUpperCase()}</Text>
              </LinearGradient>
            )}
            <View style={styles.incomingLikeTextWrap}>
              <Text style={styles.incomingLikeTitle} numberOfLines={1}>
                {receivedLikeTitle}
              </Text>
              <Text style={styles.incomingLikeSubtitle} numberOfLines={1}>
                {receivedLikeSubtitle}
              </Text>
            </View>
            <Ionicons name="heart" size={18} color="#E11D48" />
          </TouchableOpacity>
        ) : null}
      </LinearGradient>

      <View style={styles.contentArea}>
        {profileLoading ? (
          <View style={styles.centerStateCard}>
            <ActivityIndicator size="large" color={Colors.primary} />
            <Text style={styles.centerStateTitle}>Preparando tu espacio social</Text>
            <Text style={styles.centerStateText}>Estamos cargando tu perfil y los datos de la sucursal.</Text>
          </View>
        ) : socialView === 'matches' ? (
          renderMatchesView()
        ) : modoSocial ? (
          renderCurrentDiner()
        ) : (
          <View style={styles.centerStateCard}>
            <Ionicons name="people-outline" size={72} color={Colors.textMuted} />
            <Text style={styles.centerStateTitle}>Conoce a otros comensales</Text>
            <Text style={styles.centerStateText}>
              Activa el modo social para descubrir quien mas esta disfrutando de este restaurante.
            </Text>

            <TouchableOpacity style={styles.primaryButton} onPress={() => updateSocialStatus(true)} activeOpacity={0.85}>
              {statusUpdating ? (
                <ActivityIndicator size="small" color={Colors.white} />
              ) : (
                <>
                  <Ionicons name="eye-outline" size={18} color={Colors.white} />
                  <Text style={styles.primaryButtonText}>Activar modo social</Text>
                </>
              )}
            </TouchableOpacity>

            <TouchableOpacity style={styles.secondaryButton} onPress={() => setModalVisible(true)} activeOpacity={0.8}>
              <Ionicons name="create-outline" size={18} color={Colors.primary} />
              <Text style={styles.secondaryButtonText}>
                {hasCompleteProfile ? 'Editar mi perfil social' : 'Completar mi perfil'}
              </Text>
            </TouchableOpacity>
          </View>
        )}
      </View>

      <Modal visible={modalVisible} transparent animationType="slide" onRequestClose={() => setModalVisible(false)}>
        <View style={styles.modalOverlay}>
          <Pressable style={styles.modalBackdrop} onPress={() => setModalVisible(false)} />

          <View style={styles.modalCard}>
            <View style={styles.modalHandle} />

            <View style={styles.modalHeader}>
              <Text style={styles.modalTitle}>Perfil social</Text>
              <TouchableOpacity onPress={() => setModalVisible(false)} style={styles.closeButton} activeOpacity={0.8}>
                <Ionicons name="close" size={24} color={Colors.textSecondary} />
              </TouchableOpacity>
            </View>

            <ScrollView
              contentContainerStyle={styles.modalContent}
              showsVerticalScrollIndicator={false}
              keyboardShouldPersistTaps="handled"
            >
              <View style={styles.profileGalleryBlock}>
                <View style={styles.galleryHeader}>
                  <View>
                    <Text style={styles.galleryTitle}>Fotos</Text>
                    <Text style={styles.helperText}>La primera foto sera tu portada social.</Text>
                  </View>
                  <Text style={styles.galleryCount}>{socialPhotos.length}/6</Text>
                </View>

                <View style={styles.photoGrid}>
                  {socialPhotos.map((photo, index) => (
                    <View key={`${photo}-${index}`} style={styles.galleryPhotoSlot}>
                      <Image source={{ uri: photo }} style={styles.galleryPhotoImage} contentFit="cover" cachePolicy="disk" />

                      {index === 0 ? (
                        <View style={styles.primaryBadge}>
                          <Text style={styles.primaryBadgeText}>Principal</Text>
                        </View>
                      ) : (
                        <TouchableOpacity
                          style={styles.makePrimaryButton}
                          activeOpacity={0.82}
                          onPress={() => handleSetPrimaryPhoto(photo)}
                          disabled={uploading}
                        >
                          <Ionicons name="star-outline" size={15} color={Colors.white} />
                        </TouchableOpacity>
                      )}

                      <TouchableOpacity
                        style={styles.deletePhotoButton}
                        activeOpacity={0.82}
                        onPress={() => handleDeletePhoto(photo)}
                        disabled={uploading}
                      >
                        <Ionicons name="trash-outline" size={15} color={Colors.white} />
                      </TouchableOpacity>
                    </View>
                  ))}

                  {socialPhotos.length < 6 ? (
                    <TouchableOpacity
                      activeOpacity={0.86}
                      onPress={handlePickImage}
                      disabled={uploading}
                      style={[styles.galleryPhotoSlot, styles.addPhotoSlot]}
                    >
                      {uploading ? (
                        <ActivityIndicator size="small" color={Colors.primary} />
                      ) : (
                        <>
                          <Ionicons name="add" size={28} color={Colors.primary} />
                          <Text style={styles.addPhotoText}>Agregar</Text>
                        </>
                      )}
                    </TouchableOpacity>
                  ) : null}

                  {Array.from({ length: Math.max(0, 6 - socialPhotos.length - (socialPhotos.length < 6 ? 1 : 0)) }).map(
                    (_, index) => (
                      <View key={`empty-${index}`} style={[styles.galleryPhotoSlot, styles.emptyPhotoSlot]} />
                    )
                  )}
                </View>
              </View>

              <InputField
                label="Nombre"
                value={form.nombre}
                onChangeText={(value) => updateField('nombre', value.slice(0, 80))}
                placeholder="Como quieres aparecer"
                autoCapitalize="words"
                maxLength={80}
              />

              <InputField
                label="Edad"
                value={form.edad}
                onChangeText={(value) => updateField('edad', value)}
                keyboardType="number-pad"
                placeholder="Tu edad"
                maxLength={3}
              />

              <ChoiceField
                label="Sexualidad"
                value={form.sexualidad}
                placeholder="Selecciona tu sexualidad"
                options={SEXUALITY_OPTIONS}
                onSelect={(value) => updateField('sexualidad', value)}
              />

              <ChoiceField
                label="Genero"
                value={form.genero}
                placeholder="Selecciona tu genero"
                options={GENDER_OPTIONS}
                onSelect={(value) => updateField('genero', value)}
              />

              <ChoiceField
                label="Que buscas"
                value={form.queBusca}
                placeholder="Cuéntale a otros que buscas"
                options={LOOKING_FOR_OPTIONS}
                onSelect={(value) => updateField('queBusca', value)}
              />

              <InputField
                label="Descripcion"
                value={form.biografia}
                onChangeText={(value) => updateField('biografia', value)}
                placeholder="Describe tu vibra, tu plan favorito o lo que te gusta platicar."
                multiline
                numberOfLines={4}
                textAlignVertical="top"
                style={styles.textAreaInput}
              />

              <View style={styles.fieldBlock}>
                <Text style={styles.fieldLabel}>Intereses</Text>
                <Text style={styles.helperText}>Opcional. Toca los temas que mejor te representen.</Text>
                <View style={styles.interestWrap}>
                  {interestOptions.map((interest) => {
                    const active = selectedInterests.includes(interest);
                    return (
                      <TouchableOpacity
                        key={interest}
                        activeOpacity={0.85}
                        onPress={() => toggleInterest(interest)}
                        style={[styles.editorInterestChip, active && styles.editorInterestChipActive]}
                      >
                        <Ionicons
                          name={active ? 'checkmark-circle' : 'ellipse-outline'}
                          size={16}
                          color={active ? Colors.white : Colors.textMuted}
                        />
                        <Text style={[styles.editorInterestText, active && styles.editorInterestTextActive]}>
                          {interest}
                        </Text>
                      </TouchableOpacity>
                    );
                  })}
                </View>
              </View>

              <InputField
                label="Instagram"
                value={form.instagram}
                onChangeText={(value) => updateField('instagram', value)}
                placeholder="@usuario"
                autoCapitalize="none"
              />

              <InputField
                label="TikTok"
                value={form.tiktok}
                onChangeText={(value) => updateField('tiktok', value)}
                placeholder="@usuario"
                autoCapitalize="none"
              />
            </ScrollView>

            <TouchableOpacity
              style={[styles.saveButton, saving && styles.saveButtonDisabled]}
              activeOpacity={0.85}
              onPress={handleSaveProfile}
              disabled={saving}
            >
              {saving ? (
                <ActivityIndicator size="small" color={Colors.white} />
              ) : (
                <>
                  <Ionicons name="save-outline" size={18} color={Colors.white} />
                  <Text style={styles.saveButtonText}>Guardar perfil social</Text>
                </>
              )}
            </TouchableOpacity>
          </View>
        </View>
      </Modal>

      <Modal visible={consentModalVisible} transparent animationType="slide" onRequestClose={handleCancelSocialConsent}>
        <View style={styles.modalOverlay}>
          <Pressable style={styles.modalBackdrop} onPress={handleCancelSocialConsent} />

          <View style={styles.modalCard}>
            <View style={styles.modalHandle} />

            <View style={styles.modalHeader}>
              <Text style={styles.modalTitle}>Antes de activar tu perfil social</Text>
              <TouchableOpacity onPress={handleCancelSocialConsent} style={styles.closeButton} activeOpacity={0.8}>
                <Ionicons name="close" size={24} color={Colors.textSecondary} />
              </TouchableOpacity>
            </View>

            <ScrollView contentContainerStyle={styles.consentContent} showsVerticalScrollIndicator={false}>
              <View style={styles.consentIconWrap}>
                <Ionicons name="shield-checkmark-outline" size={34} color={Colors.primary} />
              </View>

              <Text style={styles.consentText}>
                Al activar el modo social, otros comensales activos de esta sucursal podran ver la informacion que
                compartas en tu perfil: nombre, fotos, edad, genero, sexualidad, descripcion, intereses, que buscas y
                tu mesa o sucursal actual.
              </Text>

              <Text style={styles.consentText}>
                Estos datos se usaran solo para las funciones sociales de la app, como descubrir comensales y enviar
                regalos. Puedes apagar el modo social cuando quieras.
              </Text>

              <TouchableOpacity style={styles.privacyNoticeButton} activeOpacity={0.82} onPress={openPrivacyNotice}>
                <Ionicons name="document-text-outline" size={18} color={Colors.primary} />
                <Text style={styles.privacyNoticeText}>Ver aviso de privacidad</Text>
              </TouchableOpacity>

              <TouchableOpacity
                style={styles.consentCheckRow}
                activeOpacity={0.85}
                onPress={() => setSocialConsentChecked((current) => !current)}
              >
                <View style={[styles.consentCheckbox, socialConsentChecked && styles.consentCheckboxActive]}>
                  {socialConsentChecked ? <Ionicons name="checkmark" size={18} color={Colors.white} /> : null}
                </View>
                <Text style={styles.consentCheckText}>
                  Acepto compartir mis datos personales para usar el modo social de Amare.
                </Text>
              </TouchableOpacity>
            </ScrollView>

            <View style={styles.filterActions}>
              <TouchableOpacity
                style={[styles.modalActionButton, styles.modalActionGhostButton]}
                activeOpacity={0.85}
                onPress={handleCancelSocialConsent}
                disabled={statusUpdating}
              >
                <Text style={styles.modalActionGhostButtonText}>Cancelar</Text>
              </TouchableOpacity>

              <TouchableOpacity
                style={[
                  styles.modalActionButton,
                  styles.modalActionPrimaryButton,
                  (!socialConsentChecked || statusUpdating) && styles.saveButtonDisabled,
                ]}
                activeOpacity={0.85}
                onPress={handleAcceptSocialConsent}
                disabled={!socialConsentChecked || statusUpdating}
              >
                {statusUpdating ? (
                  <ActivityIndicator size="small" color={Colors.white} />
                ) : (
                  <Text style={styles.saveButtonText}>Acepto y activar</Text>
                )}
              </TouchableOpacity>
            </View>
          </View>
        </View>
      </Modal>

      <Modal
        visible={mesaModalVisible}
        transparent
        animationType="slide"
        onRequestClose={() => {
          if (!statusUpdating) {
            setPendingActivationAfterBranch(false);
            setMesaModalVisible(false);
          }
        }}
      >
        <View style={styles.modalOverlay}>
          <Pressable
            style={styles.modalBackdrop}
            onPress={() => {
              if (!statusUpdating) {
                setPendingActivationAfterBranch(false);
                setMesaModalVisible(false);
              }
            }}
          />

          <View style={styles.modalCard}>
            <View style={styles.modalHandle} />

            <View style={styles.modalHeader}>
              <Text style={styles.modalTitle}>Tu mesa</Text>
              <TouchableOpacity
                onPress={() => {
                  if (!statusUpdating) {
                    setPendingActivationAfterBranch(false);
                    setMesaModalVisible(false);
                  }
                }}
                style={styles.closeButton}
                activeOpacity={0.8}
                disabled={statusUpdating}
              >
                <Ionicons name="close" size={24} color={Colors.textSecondary} />
              </TouchableOpacity>
            </View>

            <Text style={styles.helperText}>
              Comparte tu numero de mesa para ubicar mejor tu perfil en la sucursal y dejar listo el flujo de regalos
              hacia el panel de meseros.
            </Text>

            {mesaOptionsLoading ? (
              <View style={styles.giftLoadingWrap}>
                <ActivityIndicator size="large" color={Colors.primary} />
                <Text style={styles.giftLoadingText}>Buscando mesas disponibles...</Text>
              </View>
            ) : mesaOptions.length > 0 ? (
              <ChoiceField
                label="Mesa"
                value={mesaInput}
                placeholder="Selecciona tu mesa"
                options={mesaOptions}
                onSelect={(value) => setMesaInput(value)}
              />
            ) : (
              <>
                <InputField
                  label="Numero de mesa"
                  value={mesaInput}
                  onChangeText={(value) => setMesaInput(value.slice(0, 20))}
                  placeholder="Ej. 12"
                  autoCapitalize="characters"
                  autoCorrect={false}
                  returnKeyType="done"
                  onSubmitEditing={handleConfirmMesa}
                />
                <Text style={styles.helperText}>
                  Si no vemos las mesas desde la sucursal, puedes escribirla manualmente y seguir usando el modo social.
                </Text>
              </>
            )}

            <View style={styles.filterActions}>
              <TouchableOpacity
                style={[styles.modalActionButton, styles.modalActionGhostButton]}
                activeOpacity={0.85}
                onPress={() => {
                  setPendingActivationAfterBranch(false);
                  setMesaModalVisible(false);
                }}
                disabled={statusUpdating}
              >
                <Text style={styles.modalActionGhostButtonText}>Cancelar</Text>
              </TouchableOpacity>

              <TouchableOpacity
                style={[styles.modalActionButton, styles.modalActionPrimaryButton, statusUpdating && styles.saveButtonDisabled]}
                activeOpacity={0.85}
                onPress={handleConfirmMesa}
                disabled={statusUpdating}
              >
                {statusUpdating ? (
                  <ActivityIndicator size="small" color={Colors.white} />
                ) : (
                  <Text style={styles.saveButtonText}>Activar</Text>
                )}
              </TouchableOpacity>
            </View>
          </View>
        </View>
      </Modal>

      <Modal visible={receivedLikesVisible} transparent animationType="slide" onRequestClose={handleCloseReceivedLikes}>
        <View style={styles.modalOverlay}>
          <Pressable style={styles.modalBackdrop} onPress={handleCloseReceivedLikes} />

          <View style={styles.modalCard}>
            <View style={styles.modalHandle} />

            <View style={styles.modalHeader}>
              <View>
                <Text style={styles.modalTitle}>Likes recibidos</Text>
                <Text style={styles.receivedLikesModalSubtitle}>
                  {receivedLikes.length === 1
                    ? '1 persona quiere conocerte'
                    : `${receivedLikes.length} personas quieren conocerte`}
                </Text>
              </View>
              <TouchableOpacity onPress={handleCloseReceivedLikes} style={styles.closeButton} activeOpacity={0.8}>
                <Ionicons name="close" size={24} color={Colors.textSecondary} />
              </TouchableOpacity>
            </View>

            <ScrollView contentContainerStyle={styles.receivedLikesList} showsVerticalScrollIndicator={false}>
              {receivedLikes.map((like) => {
                const likedDate = formatMatchDate(like.liked_at);

                return (
                  <TouchableOpacity
                    key={like.user_id}
                    activeOpacity={0.88}
                    style={styles.matchCard}
                    onPress={() => handleReceivedLikePress(like)}
                  >
                    {like.foto_url ? (
                      <Image source={{ uri: like.foto_url }} style={styles.matchImage} contentFit="cover" cachePolicy="disk" />
                    ) : (
                      <LinearGradient colors={[Colors.primary, Colors.primaryLight]} style={styles.matchFallback}>
                        <Text style={styles.matchFallbackLetter}>{like.nombre[0]?.toUpperCase() ?? '?'}</Text>
                      </LinearGradient>
                    )}

                    <View style={styles.matchInfo}>
                      <View style={styles.matchTitleRow}>
                        <Text numberOfLines={1} style={styles.matchName}>{like.nombre}</Text>
                        <Ionicons name="heart" size={18} color="#E11D48" />
                      </View>
                      <Text numberOfLines={1} style={styles.matchMeta}>
                        {like.mesa ? `Mesa ${like.mesa}` : 'Te dio me gusta'}
                      </Text>
                      {like.que_busca ? <Text numberOfLines={1} style={styles.matchIntent}>{like.que_busca}</Text> : null}
                      {likedDate ? (
                        <Text numberOfLines={1} style={styles.matchDate}>Like recibido el {likedDate}</Text>
                      ) : null}
                    </View>

                    <Ionicons name="chevron-forward" size={20} color={Colors.textMuted} />
                  </TouchableOpacity>
                );
              })}
            </ScrollView>
          </View>
        </View>
      </Modal>

      <Modal visible={detailsVisible} transparent animationType="slide" onRequestClose={handleCloseDinerDetails}>
        <View style={styles.modalOverlay}>
          <Pressable style={styles.modalBackdrop} onPress={handleCloseDinerDetails} />

          <View style={styles.modalCard}>
            <View style={styles.modalHandle} />

            <View style={styles.modalHeader}>
              <Text style={styles.modalTitle}>Perfil del comensal</Text>
              <View style={styles.modalHeaderActions}>
                {detailDiner ? (
                  <TouchableOpacity
                    onPress={openGiftSelector}
                    style={styles.headerGiftButton}
                    activeOpacity={0.8}
                    accessibilityLabel="Enviar regalo"
                    accessibilityRole="button"
                  >
                    <Ionicons name="gift-outline" size={20} color={Colors.primary} />
                  </TouchableOpacity>
                ) : null}
                <TouchableOpacity onPress={handleCloseDinerDetails} style={styles.closeButton} activeOpacity={0.8}>
                  <Ionicons name="close" size={24} color={Colors.textSecondary} />
                </TouchableOpacity>
              </View>
            </View>

            {detailDiner ? (
              <ScrollView contentContainerStyle={styles.modalContent} showsVerticalScrollIndicator={false}>
                <View style={styles.detailHeroCard}>
                  <View style={styles.detailHeroImageWrap}>
                    {detailPhotos.length > 0 ? (
                      <>
                        <ScrollView
                          ref={detailPhotoScrollRef}
                          horizontal
                          pagingEnabled
                          showsHorizontalScrollIndicator={false}
                          style={styles.detailPhotoCarousel}
                          onMomentumScrollEnd={handleDetailPhotoScroll}
                        >
                          {detailPhotos.map((photo, index) => (
                            <Image
                              key={`${photo}-${index}`}
                              source={{ uri: photo }}
                              style={[styles.detailHeroImage, { width: detailImageWidth }]}
                              contentFit="cover"
                              cachePolicy="disk"
                            />
                          ))}
                        </ScrollView>

                        {detailPhotos.length > 1 ? (
                          <>
                            <TouchableOpacity
                              style={[styles.detailPhotoArrow, styles.detailPhotoArrowLeft]}
                              activeOpacity={0.82}
                              onPress={() => scrollDetailPhoto('prev')}
                              accessibilityRole="button"
                              accessibilityLabel="Foto anterior"
                            >
                              <Ionicons name="chevron-back" size={24} color={Colors.white} />
                            </TouchableOpacity>
                            <TouchableOpacity
                              style={[styles.detailPhotoArrow, styles.detailPhotoArrowRight]}
                              activeOpacity={0.82}
                              onPress={() => scrollDetailPhoto('next')}
                              accessibilityRole="button"
                              accessibilityLabel="Siguiente foto"
                            >
                              <Ionicons name="chevron-forward" size={24} color={Colors.white} />
                            </TouchableOpacity>
                            <View style={styles.detailPhotoCount}>
                              <Ionicons name="images-outline" size={14} color={Colors.white} />
                              <Text style={styles.detailPhotoCountText}>
                                {detailPhotoIndex + 1}/{detailPhotos.length}
                              </Text>
                            </View>
                          </>
                        ) : null}
                      </>
                    ) : (
                      <LinearGradient colors={[Colors.primary, Colors.primaryLight]} style={styles.detailHeroFallback}>
                        <Text style={styles.dinerImageFallbackLetter}>{detailDiner.nombre[0]?.toUpperCase() ?? '?'}</Text>
                      </LinearGradient>
                    )}

                    {isLikedOrMatched(detailDiner) ? (
                      <View style={styles.detailLikedBadge}>
                        <Ionicons name="heart" size={16} color={Colors.white} />
                        <Text style={styles.likedBadgeText}>
                          {detailDiner.relationship_status === 'matched' ? 'Match' : 'Like enviado'}
                        </Text>
                      </View>
                    ) : null}
                  </View>
                  {renderDinerDetailsContent(detailDiner)}
                </View>
              </ScrollView>
            ) : null}
          </View>
        </View>
      </Modal>

      <Modal visible={filtersVisible} transparent animationType="slide" onRequestClose={() => setFiltersVisible(false)}>
        <View style={styles.modalOverlay}>
          <Pressable style={styles.modalBackdrop} onPress={() => setFiltersVisible(false)} />

          <View style={styles.modalCard}>
            <View style={styles.modalHandle} />

            <View style={styles.modalHeader}>
              <Text style={styles.modalTitle}>Filtrar comensales</Text>
              <TouchableOpacity onPress={() => setFiltersVisible(false)} style={styles.closeButton} activeOpacity={0.8}>
                <Ionicons name="close" size={24} color={Colors.textSecondary} />
              </TouchableOpacity>
            </View>

            <ScrollView contentContainerStyle={styles.modalContent} showsVerticalScrollIndicator={false}>
              <Text style={styles.helperText}>
                Ajusta edad, genero o sexualidad para ver perfiles mas cercanos a lo que buscas.
              </Text>

              <View style={styles.filterRow}>
                <View style={styles.filterHalf}>
                  <InputField
                    label="Edad minima"
                    value={filters.edadMin}
                    onChangeText={(value) => updateFilter('edadMin', value.replace(/[^0-9]/g, ''))}
                    placeholder="18"
                    keyboardType="number-pad"
                  />
                </View>

                <View style={styles.filterHalf}>
                  <InputField
                    label="Edad maxima"
                    value={filters.edadMax}
                    onChangeText={(value) => updateFilter('edadMax', value.replace(/[^0-9]/g, ''))}
                    placeholder="35"
                    keyboardType="number-pad"
                  />
                </View>
              </View>

              <ChoiceField
                label="Genero"
                value={filters.genero}
                placeholder="Todos"
                options={GENDER_OPTIONS}
                onSelect={(value) => updateFilter('genero', value)}
              />

              <ChoiceField
                label="Sexualidad"
                value={filters.sexualidad}
                placeholder="Todas"
                options={SEXUALITY_OPTIONS}
                onSelect={(value) => updateFilter('sexualidad', value)}
              />
            </ScrollView>

            <View style={styles.filterActions}>
              <TouchableOpacity
                style={styles.filterGhostButton}
                activeOpacity={0.85}
                onPress={() => setFilters(EMPTY_FILTERS)}
              >
                <Text style={styles.filterGhostButtonText}>Limpiar</Text>
              </TouchableOpacity>

              <TouchableOpacity
                style={styles.saveButton}
                activeOpacity={0.85}
                onPress={() => {
                  setCurrentIndex(0);
                  setFiltersVisible(false);
                }}
              >
                <Ionicons name="options-outline" size={18} color={Colors.white} />
                <Text style={styles.saveButtonText}>Aplicar filtros</Text>
              </TouchableOpacity>
            </View>
          </View>
        </View>
      </Modal>

      <Modal visible={giftsVisible} transparent animationType="slide" onRequestClose={closeGiftSelector}>
        <View style={styles.modalOverlay}>
          <Pressable style={styles.modalBackdrop} onPress={closeGiftSelector} />

          <View style={styles.modalCard}>
            <View style={styles.modalHandle} />

            <View style={styles.modalHeader}>
              <Text style={styles.modalTitle}>Enviar regalo</Text>
              <TouchableOpacity onPress={closeGiftSelector} style={styles.closeButton} activeOpacity={0.8}>
                <Ionicons name="close" size={24} color={Colors.textSecondary} />
              </TouchableOpacity>
            </View>

            <Text style={styles.giftModalSubtitle}>
              {detailDiner ? `Elige un detalle para ${detailDiner.nombre}.` : 'Elige un detalle para este comensal.'}
            </Text>

            <ScrollView contentContainerStyle={styles.giftList} showsVerticalScrollIndicator={false}>
              {giftProductsLoading ? (
                <View style={styles.giftLoadingWrap}>
                  <ActivityIndicator size="large" color={Colors.primary} />
                  <Text style={styles.giftLoadingText}>Cargando regalos disponibles...</Text>
                </View>
              ) : giftProducts.length === 0 ? (
                <View style={styles.giftLoadingWrap}>
                  <Ionicons name="gift-outline" size={42} color={Colors.textMuted} />
                  <Text style={styles.giftLoadingText}>No encontramos regalos disponibles por ahora.</Text>
                </View>
              ) : (
                giftProducts.map((gift) => {
                  const active = gift.id === selectedGiftId;
                  return (
                    <TouchableOpacity
                      key={gift.id}
                      activeOpacity={0.85}
                      onPress={() => setSelectedGiftId(gift.id)}
                      disabled={Boolean(giftPaymentSession)}
                      style={[styles.giftItem, active && styles.giftItemActive]}
                    >
                      <View style={[styles.giftIconWrap, { backgroundColor: `${gift.color ?? Colors.primary}18` }]}>
                        {gift.imagen ? (
                          <Image source={{ uri: gift.imagen }} style={styles.giftImage} contentFit="cover" cachePolicy="disk" />
                        ) : (
                          <Ionicons name={(gift.icono as never) || 'gift'} size={22} color={gift.color ?? Colors.primary} />
                        )}
                      </View>

                      <View style={styles.giftInfo}>
                        <Text style={styles.giftName}>{gift.nombre}</Text>
                        {gift.descripcion ? <Text style={styles.giftDescription}>{gift.descripcion}</Text> : null}
                      </View>

                      <View style={styles.giftMeta}>
                        <Text style={styles.giftPrice}>${gift.precio.toFixed(2)}</Text>
                        <Ionicons
                          name={active ? 'checkmark-circle' : 'ellipse-outline'}
                          size={20}
                          color={active ? Colors.primary : Colors.textMuted}
                        />
                      </View>
                    </TouchableOpacity>
                  );
                })
              )}
            </ScrollView>

            {selectedGift ? (
              <View style={styles.giftPaymentBox}>
                <View style={styles.giftPaymentHeading}>
                  <View>
                    <Text style={styles.giftPaymentLabel}>Pago seguro con tarjeta</Text>
                    <Text style={styles.giftPaymentHint}>El mesero recibira el aviso despues de confirmar el pago.</Text>
                  </View>
                  <Text style={styles.giftPaymentTotal}>${selectedGift.precio.toFixed(2)}</Text>
                </View>
                <CardField
                  postalCodeEnabled={false}
                  placeholders={{ number: '4242 4242 4242 4242' }}
                  cardStyle={{ backgroundColor: '#FFFFFF', textColor: '#111827', borderColor: '#E2E8F0', borderWidth: 1, borderRadius: 12 }}
                  style={styles.giftCardField}
                  onCardChange={(details) => setGiftCardComplete(Boolean(details.complete))}
                />
              </View>
            ) : null}

            <TouchableOpacity
              style={[styles.saveButton, (!selectedGift || (!giftPaymentSession?.stripeConfirmed && !giftCardComplete) || giftProductsLoading || giftSending) && styles.saveButtonDisabled]}
              activeOpacity={0.85}
              onPress={handleSendGift}
              disabled={!selectedGift || (!giftPaymentSession?.stripeConfirmed && !giftCardComplete) || giftProductsLoading || giftSending}
            >
              {giftSending ? <ActivityIndicator size="small" color={Colors.white} /> : <Ionicons name="gift-outline" size={18} color={Colors.white} />}
              <Text style={styles.saveButtonText}>{giftSending ? 'Confirmando pago...' : `Pagar y enviar${selectedGift ? ` · $${selectedGift.precio.toFixed(2)}` : ''}`}</Text>
            </TouchableOpacity>
          </View>
        </View>
      </Modal>
    </SafeAreaView>
  );
}

const styles = StyleSheet.create({
  safe: {
    flex: 1,
    backgroundColor: Colors.background,
  },
  heroBackground: {
    paddingHorizontal: 20,
    paddingTop: 8,
    paddingBottom: 20,
  },
  header: {
    flexDirection: 'row',
    alignItems: 'center',
    justifyContent: 'space-between',
    marginBottom: 18,
  },
  iconButton: {
    width: 44,
    height: 44,
    borderRadius: 16,
    backgroundColor: 'rgba(255,255,255,0.86)',
    alignItems: 'center',
    justifyContent: 'center',
    borderWidth: 1,
    borderColor: '#E7EAF0',
  },
  headerTextWrap: {
    flex: 1,
    paddingHorizontal: 14,
  },
  headerTitle: {
    fontSize: 34,
    fontWeight: '800',
    color: Colors.text,
    letterSpacing: -0.9,
  },
  headerSubtitle: {
    marginTop: 2,
    fontSize: 14,
    color: Colors.textSecondary,
    fontWeight: '500',
  },
  statusCard: {
    backgroundColor: Colors.white,
    borderRadius: 26,
    padding: 16,
    borderWidth: 1,
    borderColor: '#E7EAF0',
    flexDirection: 'row',
    alignItems: 'center',
    justifyContent: 'space-between',
    ...Shadows.card,
  },
  statusTextBlock: {
    flex: 1,
    flexDirection: 'row',
    alignItems: 'flex-start',
    marginRight: 12,
  },
  statusDot: {
    width: 10,
    height: 10,
    borderRadius: 5,
    marginTop: 6,
    marginRight: 10,
  },
  statusBody: {
    flex: 1,
  },
  statusTitle: {
    fontSize: 16,
    fontWeight: '800',
    color: Colors.text,
    letterSpacing: -0.3,
  },
  statusSubtitle: {
    marginTop: 4,
    fontSize: 13,
    lineHeight: 19,
    color: Colors.textSecondary,
  },
  statusHint: {
    marginTop: 6,
    fontSize: 12,
    lineHeight: 17,
    color: Colors.textMuted,
  },
  summaryRow: {
    flexDirection: 'row',
    flexWrap: 'nowrap',
    gap: 10,
    marginTop: 14,
  },
  summaryPill: {
    flex: 1,
    minWidth: 0,
    minHeight: 42,
    borderRadius: 999,
    paddingHorizontal: 14,
    paddingVertical: 10,
    backgroundColor: '#FFFFFFCC',
    borderWidth: 1,
    borderColor: '#E7EAF0',
    flexDirection: 'row',
    alignItems: 'center',
    justifyContent: 'center',
    gap: 8,
  },
  summaryPillText: {
    flex: 1,
    fontSize: 12,
    fontWeight: '700',
    color: Colors.primary,
    textAlign: 'center',
  },
  socialViewSwitch: {
    marginTop: 14,
    borderRadius: 18,
    backgroundColor: '#FFFFFFB8',
    borderWidth: 1,
    borderColor: '#E7EAF0',
    padding: 4,
    flexDirection: 'row',
    gap: 4,
  },
  socialViewButton: {
    flex: 1,
    minHeight: 42,
    borderRadius: 15,
    alignItems: 'center',
    justifyContent: 'center',
    flexDirection: 'row',
    gap: 7,
  },
  socialViewButtonActive: {
    backgroundColor: Colors.primary,
  },
  socialViewText: {
    fontSize: 13,
    fontWeight: '800',
    color: Colors.primary,
  },
  socialViewTextActive: {
    color: Colors.white,
  },
  incomingLikeCard: {
    marginTop: 12,
    borderRadius: 18,
    backgroundColor: '#FFFFFFE8',
    borderWidth: 1,
    borderColor: '#F2D7E2',
    padding: 10,
    flexDirection: 'row',
    alignItems: 'center',
    gap: 10,
  },
  incomingLikeAvatar: {
    width: 38,
    height: 38,
    borderRadius: 19,
    backgroundColor: '#E8EBF3',
  },
  incomingLikeAvatarFallback: {
    width: 38,
    height: 38,
    borderRadius: 19,
    alignItems: 'center',
    justifyContent: 'center',
  },
  incomingLikeAvatarLetter: {
    fontSize: 16,
    fontWeight: '800',
    color: Colors.white,
  },
  incomingLikeTextWrap: {
    flex: 1,
    minWidth: 0,
  },
  incomingLikeTitle: {
    fontSize: 13,
    fontWeight: '800',
    color: Colors.text,
  },
  incomingLikeSubtitle: {
    marginTop: 2,
    fontSize: 12,
    fontWeight: '600',
    color: Colors.textSecondary,
  },
  receivedLikesModalSubtitle: {
    marginTop: 3,
    fontSize: 12,
    fontWeight: '700',
    color: Colors.textSecondary,
  },
  receivedLikesList: {
    paddingHorizontal: 20,
    paddingTop: 8,
    paddingBottom: 24,
    gap: 12,
  },
  contentArea: {
    flex: 1,
    paddingHorizontal: 20,
    paddingBottom: 24,
  },
  centerStateCard: {
    flex: 1,
    backgroundColor: Colors.white,
    borderRadius: 30,
    borderWidth: 1,
    borderColor: '#E7EAF0',
    marginTop: 10,
    paddingHorizontal: 26,
    paddingVertical: 30,
    alignItems: 'center',
    justifyContent: 'center',
    ...Shadows.card,
  },
  centerStateTitle: {
    marginTop: 18,
    fontSize: 20,
    fontWeight: '800',
    color: Colors.text,
    textAlign: 'center',
    letterSpacing: -0.5,
  },
  centerStateText: {
    marginTop: 10,
    fontSize: 16,
    lineHeight: 24,
    color: Colors.textSecondary,
    textAlign: 'center',
  },
  primaryButton: {
    marginTop: 24,
    minHeight: 58,
    borderRadius: 22,
    backgroundColor: Colors.primary,
    paddingHorizontal: 22,
    alignItems: 'center',
    justifyContent: 'center',
    flexDirection: 'row',
    gap: 8,
    ...Shadows.md,
  },
  primaryButtonText: {
    fontSize: 17,
    fontWeight: '800',
    color: Colors.white,
    letterSpacing: -0.3,
  },
  secondaryButton: {
    marginTop: 14,
    minHeight: 52,
    borderRadius: 20,
    borderWidth: 1,
    borderColor: '#D8DDE8',
    backgroundColor: '#F9FAFC',
    paddingHorizontal: 18,
    alignItems: 'center',
    justifyContent: 'center',
    flexDirection: 'row',
    gap: 8,
  },
  secondaryButtonText: {
    fontSize: 15,
    fontWeight: '700',
    color: Colors.primary,
  },
  carouselArea: {
    flex: 1,
    paddingTop: 6,
  },
  counterText: {
    marginBottom: 12,
    fontSize: 16,
    fontWeight: '700',
    color: Colors.textSecondary,
  },
  tinderCardContainer: {
    flex: 1,
    justifyContent: 'center',
    alignItems: 'center',
  },
  tinderCard: {
    width: '100%',
    height: '100%',
    maxHeight: 620,
  },
  discoveryActions: {
    flexDirection: 'row',
    gap: 12,
    paddingTop: 14,
    paddingBottom: 2,
  },
  discoveryArrowButton: {
    width: 58,
    minHeight: 56,
    borderRadius: 20,
    borderWidth: 1,
    borderColor: '#D8DDE8',
    backgroundColor: Colors.white,
    alignItems: 'center',
    justifyContent: 'center',
    flexDirection: 'row',
  },
  discoveryArrowButtonDisabled: {
    opacity: 0.45,
  },
  likeButton: {
    flex: 1,
    minHeight: 56,
    borderRadius: 20,
    backgroundColor: '#E11D48',
    alignItems: 'center',
    justifyContent: 'center',
    flexDirection: 'row',
    gap: 8,
    ...Shadows.md,
  },
  likeButtonText: {
    fontSize: 16,
    fontWeight: '800',
    color: Colors.white,
  },
  dinerCard: {
    minHeight: 560,
    borderRadius: 30,
    backgroundColor: Colors.white,
    borderWidth: 1,
    borderColor: '#E7EAF0',
    overflow: 'hidden',
    ...Shadows.lg,
  },
  dinerImageWrap: {
    height: 360,
    backgroundColor: '#E8EBF3',
  },
  dinerImage: {
    width: '100%',
    height: '100%',
  },
  dinerImageFallback: {
    flex: 1,
    alignItems: 'center',
    justifyContent: 'center',
  },
  likedBadge: {
    position: 'absolute',
    top: 14,
    right: 14,
    minHeight: 34,
    borderRadius: 999,
    backgroundColor: 'rgba(225, 29, 72, 0.92)',
    paddingHorizontal: 12,
    flexDirection: 'row',
    alignItems: 'center',
    gap: 6,
    zIndex: 2,
  },
  likedBadgeText: {
    fontSize: 13,
    fontWeight: '800',
    color: Colors.white,
  },
  detailLikedBadge: {
    position: 'absolute',
    top: 14,
    left: 14,
    minHeight: 34,
    borderRadius: 999,
    backgroundColor: 'rgba(225, 29, 72, 0.92)',
    paddingHorizontal: 12,
    flexDirection: 'row',
    alignItems: 'center',
    gap: 6,
    zIndex: 5,
  },
  dinerImageOverlay: {
    ...StyleSheet.absoluteFillObject,
    justifyContent: 'flex-end',
    paddingHorizontal: 20,
    paddingVertical: 20,
  },
  dinerImageName: {
    fontSize: 28,
    fontWeight: '800',
    color: Colors.white,
    letterSpacing: -0.7,
  },
  dinerImageHint: {
    marginTop: 6,
    fontSize: 14,
    fontWeight: '600',
    color: 'rgba(255,255,255,0.88)',
  },
  dinerImageFallbackLetter: {
    fontSize: 96,
    fontWeight: '800',
    color: Colors.white,
  },
  dinerPreview: {
    paddingHorizontal: 22,
    paddingVertical: 20,
  },
  previewEyebrow: {
    fontSize: 15,
    fontWeight: '800',
    color: Colors.accentDark,
    letterSpacing: -0.2,
  },
  previewMetaWrap: {
    flexDirection: 'row',
    flexWrap: 'wrap',
    alignItems: 'center',
    gap: 8,
    marginTop: 12,
  },
  previewText: {
    marginTop: 12,
    fontSize: 14,
    lineHeight: 21,
    color: Colors.textSecondary,
  },
  dinerContent: {
    paddingHorizontal: 22,
    paddingVertical: 18,
  },
  dinerName: {
    fontSize: 24,
    fontWeight: '800',
    color: Colors.text,
    letterSpacing: -0.6,
  },
  dinerSubtitle: {
    marginTop: 4,
    fontSize: 16,
    fontWeight: '700',
    color: Colors.accentDark,
  },
  metaWrap: {
    flexDirection: 'row',
    flexWrap: 'wrap',
    gap: 8,
    marginTop: 16,
  },
  metaChip: {
    borderRadius: 999,
    backgroundColor: '#EEF2FF',
    paddingHorizontal: 14,
    minHeight: 34,
    paddingVertical: 7,
    alignItems: 'center',
    justifyContent: 'center',
  },
  metaChipInteractive: {
    flexDirection: 'row',
    gap: 5,
  },
  metaChipText: {
    fontSize: 14,
    lineHeight: 18,
    fontWeight: '700',
    color: Colors.primary,
  },
  descriptionText: {
    marginTop: 16,
    fontSize: 16,
    lineHeight: 23,
    color: Colors.textSecondary,
  },
  infoSection: {
    marginTop: 18,
  },
  infoLabel: {
    fontSize: 12,
    fontWeight: '800',
    textTransform: 'uppercase',
    letterSpacing: 1.1,
    color: Colors.textMuted,
    marginBottom: 8,
  },
  infoValue: {
    fontSize: 16,
    fontWeight: '600',
    color: Colors.text,
  },
  interestWrap: {
    flexDirection: 'row',
    flexWrap: 'wrap',
    gap: 10,
  },
  interestChip: {
    borderRadius: 999,
    borderWidth: 1,
    borderColor: '#D9DDE6',
    backgroundColor: '#FBFCFE',
    paddingHorizontal: 14,
    paddingVertical: 9,
  },
  interestChipText: {
    fontSize: 14,
    color: Colors.textSecondary,
    fontWeight: '600',
  },
  likeDetailButton: {
    minHeight: 56,
    borderRadius: 20,
    backgroundColor: '#E11D48',
    marginTop: 18,
    flexDirection: 'row',
    alignItems: 'center',
    justifyContent: 'center',
    gap: 8,
    ...Shadows.md,
  },
  giftButton: {
    minHeight: 56,
    borderRadius: 20,
    backgroundColor: Colors.primary,
    marginTop: 18,
    marginBottom: 8,
    flexDirection: 'row',
    alignItems: 'center',
    justifyContent: 'center',
    gap: 8,
    ...Shadows.md,
  },
  giftButtonText: {
    fontSize: 16,
    fontWeight: '800',
    color: Colors.white,
  },
  detailHeroCard: {
    borderRadius: 28,
    borderWidth: 1,
    borderColor: '#E7EAF0',
    overflow: 'hidden',
    backgroundColor: Colors.white,
  },
  detailHeroImageWrap: {
    height: 360,
    backgroundColor: '#E8EBF3',
    position: 'relative',
  },
  detailPhotoCarousel: {
    flex: 1,
  },
  detailHeroImage: {
    height: '100%',
  },
  detailPhotoArrow: {
    position: 'absolute',
    top: '45%',
    zIndex: 4,
    width: 42,
    height: 42,
    borderRadius: 21,
    backgroundColor: 'rgba(17, 24, 39, 0.52)',
    alignItems: 'center',
    justifyContent: 'center',
  },
  detailPhotoArrowLeft: {
    left: 12,
  },
  detailPhotoArrowRight: {
    right: 12,
  },
  detailPhotoCount: {
    position: 'absolute',
    right: 14,
    top: 14,
    minHeight: 30,
    borderRadius: 999,
    backgroundColor: 'rgba(17, 24, 39, 0.64)',
    paddingHorizontal: 11,
    flexDirection: 'row',
    alignItems: 'center',
    gap: 5,
  },
  detailPhotoCountText: {
    fontSize: 13,
    fontWeight: '800',
    color: Colors.white,
  },
  detailHeroFallback: {
    flex: 1,
    alignItems: 'center',
    justifyContent: 'center',
  },
  matchesList: {
    paddingTop: 10,
    paddingBottom: 26,
    gap: 12,
  },
  matchCard: {
    borderRadius: 22,
    borderWidth: 1,
    borderColor: '#E7EAF0',
    backgroundColor: Colors.white,
    padding: 12,
    flexDirection: 'row',
    alignItems: 'center',
    ...Shadows.card,
  },
  matchImage: {
    width: 72,
    height: 72,
    borderRadius: 18,
    backgroundColor: '#E8EBF3',
  },
  matchFallback: {
    width: 72,
    height: 72,
    borderRadius: 18,
    alignItems: 'center',
    justifyContent: 'center',
  },
  matchFallbackLetter: {
    fontSize: 30,
    fontWeight: '800',
    color: Colors.white,
  },
  matchInfo: {
    flex: 1,
    marginHorizontal: 12,
  },
  matchTitleRow: {
    flexDirection: 'row',
    alignItems: 'center',
    gap: 7,
  },
  matchName: {
    flex: 1,
    fontSize: 18,
    fontWeight: '800',
    color: Colors.text,
    letterSpacing: -0.4,
  },
  matchMeta: {
    marginTop: 5,
    fontSize: 13,
    fontWeight: '700',
    color: Colors.primary,
  },
  matchIntent: {
    marginTop: 3,
    fontSize: 13,
    color: Colors.textSecondary,
  },
  matchDate: {
    marginTop: 3,
    fontSize: 12,
    fontWeight: '700',
    color: Colors.textMuted,
  },
  giftModalSubtitle: {
    marginTop: -2,
    marginBottom: 10,
    fontSize: 14,
    color: Colors.textSecondary,
  },
  giftList: {
    paddingBottom: 12,
    gap: 10,
  },
  giftLoadingWrap: {
    minHeight: 220,
    alignItems: 'center',
    justifyContent: 'center',
    paddingHorizontal: 20,
  },
  giftLoadingText: {
    marginTop: 12,
    fontSize: 14,
    lineHeight: 21,
    color: Colors.textSecondary,
    textAlign: 'center',
  },
  giftItem: {
    borderRadius: 20,
    borderWidth: 1,
    borderColor: '#E7EAF0',
    backgroundColor: '#FBFCFE',
    padding: 14,
    flexDirection: 'row',
    alignItems: 'center',
  },
  giftItemActive: {
    borderColor: Colors.primary,
    backgroundColor: '#F5F7FF',
  },
  giftIconWrap: {
    width: 56,
    height: 56,
    borderRadius: 18,
    alignItems: 'center',
    justifyContent: 'center',
    overflow: 'hidden',
    marginRight: 12,
  },
  giftImage: {
    width: '100%',
    height: '100%',
  },
  giftInfo: {
    flex: 1,
    marginRight: 12,
  },
  giftName: {
    fontSize: 15,
    fontWeight: '800',
    color: Colors.text,
  },
  giftDescription: {
    marginTop: 4,
    fontSize: 13,
    lineHeight: 18,
    color: Colors.textSecondary,
  },
  giftMeta: {
    alignItems: 'flex-end',
    gap: 6,
  },
  giftPaymentBox: { marginTop: 12, padding: 12, borderRadius: 16, backgroundColor: '#F8FAFC', borderWidth: 1, borderColor: '#E2E8F0' },
  giftPaymentHeading: { flexDirection: 'row', alignItems: 'center', justifyContent: 'space-between', gap: 12 },
  giftPaymentLabel: { color: '#111827', fontSize: 13, fontWeight: '800' },
  giftPaymentHint: { marginTop: 2, maxWidth: 230, color: '#64748B', fontSize: 10, lineHeight: 14 },
  giftPaymentTotal: { color: Colors.primary, fontSize: 18, fontWeight: '900' },
  giftCardField: { width: '100%', height: 52, marginTop: 10 },
  giftPrice: {
    fontSize: 15,
    fontWeight: '800',
    color: Colors.primary,
  },
  modalOverlay: {
    flex: 1,
    justifyContent: 'flex-end',
    backgroundColor: 'rgba(18, 18, 42, 0.18)',
  },
  modalBackdrop: {
    flex: 1,
  },
  modalCard: {
    maxHeight: '92%',
    backgroundColor: Colors.white,
    borderTopLeftRadius: 30,
    borderTopRightRadius: 30,
    paddingHorizontal: 18,
    paddingTop: 10,
    paddingBottom: 18,
  },
  modalHandle: {
    width: 54,
    height: 6,
    borderRadius: 999,
    backgroundColor: '#D9DDE6',
    alignSelf: 'center',
    marginBottom: 8,
  },
  modalHeader: {
    flexDirection: 'row',
    alignItems: 'center',
    justifyContent: 'space-between',
    paddingBottom: 10,
  },
  modalTitle: {
    fontSize: 18,
    fontWeight: '800',
    color: Colors.text,
  },
  modalHeaderActions: {
    flexDirection: 'row',
    alignItems: 'center',
    gap: 4,
  },
  headerGiftButton: {
    width: 40,
    height: 40,
    borderRadius: 20,
    backgroundColor: '#F7F0F2',
    borderWidth: 1,
    borderColor: '#F0DCE3',
    alignItems: 'center',
    justifyContent: 'center',
  },
  closeButton: {
    width: 40,
    height: 40,
    borderRadius: 20,
    alignItems: 'center',
    justifyContent: 'center',
  },
  modalContent: {
    paddingBottom: 12,
  },
  consentContent: {
    paddingBottom: 8,
  },
  consentIconWrap: {
    width: 58,
    height: 58,
    borderRadius: 20,
    backgroundColor: '#EEF2FF',
    alignItems: 'center',
    justifyContent: 'center',
    marginBottom: 14,
  },
  consentText: {
    fontSize: 15,
    lineHeight: 22,
    color: Colors.textSecondary,
    marginBottom: 12,
  },
  privacyNoticeButton: {
    minHeight: 46,
    borderRadius: 16,
    borderWidth: 1,
    borderColor: '#D8DDE8',
    backgroundColor: '#F9FAFC',
    paddingHorizontal: 14,
    marginTop: 2,
    marginBottom: 14,
    flexDirection: 'row',
    alignItems: 'center',
    justifyContent: 'center',
    gap: 8,
  },
  privacyNoticeText: {
    fontSize: 14,
    fontWeight: '800',
    color: Colors.primary,
  },
  consentCheckRow: {
    borderRadius: 18,
    borderWidth: 1,
    borderColor: '#E1E5ED',
    backgroundColor: '#FBFCFE',
    padding: 14,
    flexDirection: 'row',
    alignItems: 'flex-start',
    gap: 12,
  },
  consentCheckbox: {
    width: 26,
    height: 26,
    borderRadius: 8,
    borderWidth: 1.5,
    borderColor: '#CBD5E1',
    backgroundColor: Colors.white,
    alignItems: 'center',
    justifyContent: 'center',
    marginTop: 1,
  },
  consentCheckboxActive: {
    borderColor: Colors.primary,
    backgroundColor: Colors.primary,
  },
  consentCheckText: {
    flex: 1,
    fontSize: 14,
    lineHeight: 20,
    fontWeight: '700',
    color: Colors.text,
  },
  profileHero: {
    alignItems: 'center',
    marginBottom: 18,
  },
  profilePhotoWrap: {
    alignItems: 'center',
  },
  profilePhoto: {
    width: 126,
    height: 126,
    borderRadius: 63,
    overflow: 'hidden',
    borderWidth: 4,
    borderColor: Colors.white,
    backgroundColor: '#E7EAF0',
    ...Shadows.md,
  },
  profilePhotoImage: {
    width: '100%',
    height: '100%',
  },
  profilePhotoFallback: {
    flex: 1,
    alignItems: 'center',
    justifyContent: 'center',
  },
  profilePhotoLetter: {
    fontSize: 44,
    fontWeight: '800',
    color: Colors.white,
  },
  photoButton: {
    marginTop: 12,
    minHeight: 46,
    borderRadius: 16,
    backgroundColor: Colors.primary,
    paddingHorizontal: 18,
    alignItems: 'center',
    justifyContent: 'center',
    flexDirection: 'row',
    gap: 8,
  },
  photoButtonText: {
    fontSize: 15,
    fontWeight: '700',
    color: Colors.white,
  },
  profileGalleryBlock: {
    marginBottom: 18,
  },
  galleryHeader: {
    marginBottom: 12,
    flexDirection: 'row',
    alignItems: 'flex-start',
    justifyContent: 'space-between',
    gap: 12,
  },
  galleryTitle: {
    fontSize: 15,
    fontWeight: '800',
    color: Colors.text,
  },
  galleryCount: {
    minWidth: 44,
    minHeight: 30,
    borderRadius: 999,
    backgroundColor: '#F4F6FA',
    textAlign: 'center',
    textAlignVertical: 'center',
    paddingHorizontal: 10,
    paddingTop: Platform.OS === 'ios' ? 6 : 5,
    fontSize: 13,
    fontWeight: '800',
    color: Colors.textSecondary,
  },
  photoGrid: {
    flexDirection: 'row',
    flexWrap: 'wrap',
    gap: 10,
  },
  galleryPhotoSlot: {
    width: '31%',
    aspectRatio: 1,
    borderRadius: 18,
    overflow: 'hidden',
    backgroundColor: '#EEF1F6',
    borderWidth: 1,
    borderColor: '#E1E5ED',
  },
  galleryPhotoImage: {
    width: '100%',
    height: '100%',
  },
  addPhotoSlot: {
    alignItems: 'center',
    justifyContent: 'center',
    borderStyle: 'dashed',
    backgroundColor: '#FAFBFD',
  },
  addPhotoText: {
    marginTop: 4,
    fontSize: 12,
    fontWeight: '800',
    color: Colors.primary,
  },
  emptyPhotoSlot: {
    backgroundColor: '#F7F8FB',
    opacity: 0.75,
  },
  primaryBadge: {
    position: 'absolute',
    left: 8,
    top: 8,
    minHeight: 24,
    borderRadius: 999,
    backgroundColor: 'rgba(17, 24, 39, 0.68)',
    paddingHorizontal: 9,
    justifyContent: 'center',
  },
  primaryBadgeText: {
    fontSize: 11,
    fontWeight: '800',
    color: Colors.white,
  },
  makePrimaryButton: {
    position: 'absolute',
    left: 8,
    top: 8,
    width: 30,
    height: 30,
    borderRadius: 15,
    backgroundColor: 'rgba(17, 24, 39, 0.68)',
    alignItems: 'center',
    justifyContent: 'center',
  },
  deletePhotoButton: {
    position: 'absolute',
    right: 8,
    top: 8,
    width: 30,
    height: 30,
    borderRadius: 15,
    backgroundColor: 'rgba(190, 18, 60, 0.82)',
    alignItems: 'center',
    justifyContent: 'center',
  },
  fieldBlock: {
    marginBottom: 18,
  },
  fieldLabelRow: {
    flexDirection: 'row',
    alignItems: 'center',
    gap: 6,
    marginBottom: 6,
  },
  fieldLabel: {
    fontSize: 13,
    fontWeight: '700',
    color: '#374151',
    letterSpacing: 0.3,
    textTransform: 'uppercase',
  },
  fieldInfoButton: {
    width: 28,
    height: 28,
    borderRadius: 14,
    alignItems: 'center',
    justifyContent: 'center',
  },
  helperText: {
    marginBottom: 10,
    fontSize: 13,
    color: Colors.textSecondary,
  },
  choiceButton: {
    minHeight: 56,
    borderRadius: 16,
    borderWidth: 1.5,
    borderColor: '#E5E7EB',
    paddingHorizontal: 16,
    flexDirection: 'row',
    alignItems: 'center',
    justifyContent: 'space-between',
    backgroundColor: Colors.white,
  },
  choiceValue: {
    flex: 1,
    marginRight: 12,
    fontSize: 16,
    fontWeight: '500',
    color: Colors.text,
  },
  choicePlaceholder: {
    color: '#9CA3AF',
    fontWeight: '400',
  },
  choiceDescription: {
    marginTop: 8,
    fontSize: 12,
    lineHeight: 17,
    color: Colors.textMuted,
  },
  choiceList: {
    marginTop: 8,
    borderWidth: 1,
    borderColor: '#E5E7EB',
    borderRadius: 16,
    overflow: 'hidden',
    backgroundColor: Colors.white,
  },
  choiceItem: {
    minHeight: 52,
    paddingHorizontal: 16,
    flexDirection: 'row',
    alignItems: 'center',
    justifyContent: 'space-between',
    borderBottomWidth: 1,
    borderBottomColor: '#F2F4F7',
  },
  choiceItemActive: {
    backgroundColor: '#F8FAFC',
  },
  choiceItemLast: {
    borderBottomWidth: 0,
  },
  choiceItemText: {
    fontSize: 15,
    fontWeight: '500',
    color: Colors.text,
  },
  choiceItemTextActive: {
    fontWeight: '700',
    color: Colors.primary,
  },
  filterRow: {
    flexDirection: 'row',
    gap: 12,
  },
  filterHalf: {
    flex: 1,
  },
  filterActions: {
    flexDirection: 'row',
    alignItems: 'center',
    gap: 12,
    marginTop: 10,
  },
  modalActionButton: {
    flex: 1,
    minHeight: 56,
    borderRadius: 20,
    alignItems: 'center',
    justifyContent: 'center',
  },
  modalActionGhostButton: {
    borderWidth: 1,
    borderColor: '#D8DDE8',
    backgroundColor: '#F9FAFC',
    paddingHorizontal: 20,
  },
  modalActionGhostButtonText: {
    fontSize: 15,
    fontWeight: '700',
    color: Colors.primary,
  },
  modalActionPrimaryButton: {
    backgroundColor: Colors.primary,
    paddingHorizontal: 20,
    marginTop: 0,
    ...Shadows.md,
  },
  filterGhostButton: {
    minHeight: 56,
    borderRadius: 20,
    borderWidth: 1,
    borderColor: '#D8DDE8',
    backgroundColor: '#F9FAFC',
    paddingHorizontal: 20,
    alignItems: 'center',
    justifyContent: 'center',
  },
  filterGhostButtonText: {
    fontSize: 15,
    fontWeight: '700',
    color: Colors.primary,
  },
  textAreaInput: {
    minHeight: 92,
    paddingTop: 14,
  },
  editorInterestChip: {
    borderRadius: 999,
    paddingHorizontal: 14,
    paddingVertical: 10,
    borderWidth: 1,
    borderColor: '#D7DCE6',
    backgroundColor: '#FBFCFE',
    flexDirection: 'row',
    alignItems: 'center',
    gap: 8,
  },
  editorInterestChipActive: {
    backgroundColor: Colors.primary,
    borderColor: Colors.primary,
  },
  editorInterestText: {
    fontSize: 14,
    fontWeight: '600',
    color: Colors.textSecondary,
  },
  editorInterestTextActive: {
    color: Colors.white,
  },
  saveButton: {
    minHeight: 56,
    borderRadius: 20,
    backgroundColor: Colors.primary,
    alignItems: 'center',
    justifyContent: 'center',
    flexDirection: 'row',
    gap: 8,
    marginTop: 10,
    ...Shadows.md,
  },
  saveButtonDisabled: {
    opacity: 0.65,
  },
  saveButtonText: {
    fontSize: 16,
    fontWeight: '800',
    color: Colors.white,
  },
});
