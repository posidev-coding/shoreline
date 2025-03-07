import type { Express } from "express";
import { createServer } from "http";
import { insertContactSchema } from "@shared/schema";
import { storage } from "./storage";

export async function registerRoutes(app: Express) {
  app.post("/api/contact", async (req, res) => {
    try {
      const contact = insertContactSchema.parse(req.body);
      const submission = await storage.createContact(contact);
      res.json({ success: true, data: submission });
    } catch (error) {
      res.status(400).json({ success: false, error: String(error) });
    }
  });

  return createServer(app);
}
