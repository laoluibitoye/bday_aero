import React from 'react';
import { createRoot } from 'react-dom/client';
import { App } from './App';
import './styles.css';

const mount = document.getElementById('aero-paywall-admin-app');
if (mount) {
  createRoot(mount).render(
    <React.StrictMode>
      <App />
    </React.StrictMode>,
  );
}
