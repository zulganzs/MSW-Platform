import { useEffect, useState } from 'react';
import { Platform, Pressable, Text, View } from 'react-native';

// ponytail: beforeinstallprompt is not in standard DOM types — ceiling: W3C PWA spec, upgrade when TC49 lands
interface BeforeInstallPromptEvent extends Event {
  prompt: () => Promise<void>;
  userChoice: Promise<{ outcome: 'accepted' | 'dismissed' }>;
}

const DISMISS_KEY = 'pwa-install-dismissed';

function isStandalone(): boolean {
  if (typeof window === 'undefined') return false;
  return (
    window.matchMedia?.('(display-mode: standalone)').matches === true ||
    // iOS Safari
    (window.navigator as unknown as { standalone?: boolean }).standalone === true
  );
}

function isMobileScreen(): boolean {
  if (typeof window === 'undefined') return false;
  return window.matchMedia?.('(max-width: 768px)')?.matches === true;
}

function isIOS(): boolean {
  if (typeof window === 'undefined') return false;
  const ua = window.navigator.userAgent;
  return /iPhone|iPad|iPod/.test(ua) && !/CriOS|FxiOS/.test(ua);
}

export function PwaInstallPrompt() {
  const [deferredPrompt, setDeferredPrompt] = useState<BeforeInstallPromptEvent | null>(null);
  const [showOverlay, setShowOverlay] = useState(false);
  const [showIOSInstructions, setShowIOSInstructions] = useState(false);

  useEffect(() => {
    if (Platform.OS !== 'web') return;
    if (isStandalone()) return;

    // Respect previous dismissal
    try {
      if (localStorage.getItem(DISMISS_KEY) === '1') return;
    } catch {
      // localStorage may be blocked — proceed
    }

    const handler = (e: Event) => {
      e.preventDefault();
      setDeferredPrompt(e as BeforeInstallPromptEvent);
      if (isMobileScreen()) setShowOverlay(true);
    };

    window.addEventListener('beforeinstallprompt', handler);

    // iOS Safari never fires beforeinstallprompt — show instructions instead
    if (isIOS() && isMobileScreen()) {
      const t = setTimeout(() => setShowIOSInstructions(true), 1500);
      return () => {
        window.removeEventListener('beforeinstallprompt', handler);
        clearTimeout(t);
      };
    }

    return () => window.removeEventListener('beforeinstallprompt', handler);
  }, []);

  const handleInstall = async () => {
    if (!deferredPrompt) return;
    await deferredPrompt.prompt();
    const choice = await deferredPrompt.userChoice;
    if (choice.outcome === 'accepted') {
      setShowOverlay(false);
    }
    setDeferredPrompt(null);
  };

  const handleDismiss = () => {
    setShowOverlay(false);
    setShowIOSInstructions(false);
    try {
      localStorage.setItem(DISMISS_KEY, '1');
    } catch {
      // ignore
    }
  };

  if (Platform.OS !== 'web') return null;

  // Android/Chrome/Edge — fullscreen install overlay
  if (showOverlay && deferredPrompt) {
    return (
      <View
        style={{
          position: 'absolute',
          top: 0,
          left: 0,
          right: 0,
          bottom: 0,
          zIndex: 9999,
          backgroundColor: '#ffffff',
          alignItems: 'center',
          justifyContent: 'center',
          padding: 24,
        }}
      >
        <View style={{ alignItems: 'center', maxWidth: 320, width: '100%' }}>
          {/* App icon */}
          <View
            style={{
              width: 96,
              height: 96,
              borderRadius: 24,
              backgroundColor: '#eff6ff',
              alignItems: 'center',
              justifyContent: 'center',
              marginBottom: 24,
            }}
          >
            <Text style={{ fontSize: 40 }}>♻️</Text>
          </View>

          <Text
            style={{
              fontSize: 24,
              fontWeight: '700',
              color: '#1e3a8a',
              textAlign: 'center',
              marginBottom: 8,
            }}
          >
            Pasang MSW Platform
          </Text>
          <Text
            style={{
              fontSize: 16,
              color: '#64748b',
              textAlign: 'center',
              marginBottom: 32,
              lineHeight: 22,
            }}
          >
            Instal aplikasi untuk akses lebih cepat dan notifikasi laporan
          </Text>

          {/* Install button — center screen */}
          <Pressable
            onPress={handleInstall}
            style={{
              backgroundColor: '#2563eb',
              paddingHorizontal: 48,
              paddingVertical: 16,
              borderRadius: 12,
              marginBottom: 16,
              width: '100%',
              alignItems: 'center',
            }}
          >
            <Text style={{ color: '#ffffff', fontSize: 18, fontWeight: '600' }}>
              Pasang Aplikasi
            </Text>
          </Pressable>

          <Pressable onPress={handleDismiss}>
            <Text style={{ color: '#64748b', fontSize: 14 }}>Lanjut tanpa instal</Text>
          </Pressable>
        </View>
      </View>
    );
  }

  // iOS Safari — instructions overlay
  if (showIOSInstructions) {
    return (
      <View
        style={{
          position: 'absolute',
          top: 0,
          left: 0,
          right: 0,
          bottom: 0,
          zIndex: 9999,
          backgroundColor: '#ffffff',
          alignItems: 'center',
          justifyContent: 'center',
          padding: 24,
        }}
      >
        <View style={{ alignItems: 'center', maxWidth: 320, width: '100%' }}>
          <View
            style={{
              width: 96,
              height: 96,
              borderRadius: 24,
              backgroundColor: '#eff6ff',
              alignItems: 'center',
              justifyContent: 'center',
              marginBottom: 24,
            }}
          >
            <Text style={{ fontSize: 40 }}>♻️</Text>
          </View>

          <Text
            style={{
              fontSize: 24,
              fontWeight: '700',
              color: '#1e3a8a',
              textAlign: 'center',
              marginBottom: 8,
            }}
          >
            Pasang MSW Platform
          </Text>
          <Text
            style={{
              fontSize: 16,
              color: '#64748b',
              textAlign: 'center',
              marginBottom: 24,
              lineHeight: 22,
            }}
          >
            Untuk memasang aplikasi di iPhone:
          </Text>

          <View style={{ marginBottom: 24, alignSelf: 'stretch' }}>
            <Text style={{ color: '#475569', fontSize: 15, marginBottom: 8, lineHeight: 22 }}>
              {'1. Ketuk tombol Bagikan '}
              <Text style={{ fontSize: 18 }}>⎙</Text>
              {' di toolbar Safari'}
            </Text>
            <Text style={{ color: '#475569', fontSize: 15, marginBottom: 8, lineHeight: 22 }}>
              {'2. Pilih "Tambah ke Layar Utama"'}
            </Text>
            <Text style={{ color: '#475569', fontSize: 15, lineHeight: 22 }}>
              {'3. Ketuk "Tambah" untuk memasang'}
            </Text>
          </View>

          <Pressable onPress={handleDismiss}>
            <Text style={{ color: '#64748b', fontSize: 14 }}>Lanjut tanpa instal</Text>
          </Pressable>
        </View>
      </View>
    );
  }

  return null;
}
