import React from 'react';
export const Button: React.FC<React.ButtonHTMLAttributes<HTMLButtonElement>> = (p) => (
  <button {...p} style={{ padding: '8px 12px', borderRadius: 8 }} />
);
