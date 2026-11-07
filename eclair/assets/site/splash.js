// SPDX-License-Identifier: AGPL-3.0-only
// Copyright (c) 2026 Thierry Gustin
/* ⚡ Éclair — écran de démarrage : seulement à la première page d'une visite (lu avant l'affichage, quelques octets). */
try {
  if (sessionStorage.getItem('eclair-splash')) document.documentElement.classList.add('no-splash');
  else sessionStorage.setItem('eclair-splash', '1');
} catch (e) {
  document.documentElement.classList.add('no-splash');
}
