/** @type {import('next').NextConfig} */
const nextConfig = {
  output: 'export', // Static export for Cloudflare Pages
  images: {
    unoptimized: true, // Cloudflare Images handles optimization
  },
  trailingSlash: true,
};
export default nextConfig;
