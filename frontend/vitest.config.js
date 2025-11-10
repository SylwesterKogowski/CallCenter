import { defineConfig } from 'vite';
import react from '@vitejs/plugin-react';

export default defineConfig({
  plugins: [react()],
  test: {
    // 1. Specify the environment to use (JSDOM simulates the browser DOM)
    environment: 'jsdom',
    
    // 2. Specify the file that runs before all tests
    setupFiles: './app/setupTests.js', 
    
    // 3. Optional: Configure test file patterns
    globals: true, // Enables global APIs like 'describe', 'it', 'expect'
  },
});