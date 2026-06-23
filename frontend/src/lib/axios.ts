import axios from 'axios'

export const api = axios.create({
  // Em um cenário real, isso viria do .env (ex: import.meta.env.VITE_API_URL)
  baseURL: 'http://localhost:8000/api', 
})