import { Hono } from 'hono'
import { zValidator } from "@hono/zod-validator";
import { z } from "zod";


const hardwareSchema = z
    .object({
        id: z.number().min(1),
        name: z.string().min(3).max(255),
        hwTypeId: z.number().int().positive(),
        cost: z.number().nonnegative()
    });

const hardwareTypeSchema = z
    .object({
        id: z.number().min(1),
        name: z.string().min(3).max(255),
    })

const createPostSchema = hardwareSchema.omit({id: true})
    .superRefine((data, ctx) => {
        const exists = fakeHardwareTypes.some(t => t.id === data.hwTypeId)
        if(!exists){
            ctx.addIssue({
                code: z.ZodIssueCode.custom,
                path: ["hwTypeId"],
                message: `HardwareType ${data.hwTypeId} does not exist`
            })
        }
    })

type Hardware = z.infer<typeof hardwareSchema>;
type HardwareType = z.infer<typeof hardwareTypeSchema>;

const fakeHardwareTypes:  HardwareType[] = [
    {id: 1, name: "Laptop"},
    {id: 2, name: "Desktop"},
    {id: 3, name: "Server"},
    {id: 4, name: "Phone"}
]

const fakeHardwares: Hardware[] = [
    {id: 1, name: "UKJDOE25", hwTypeId: 0, cost: 1000},
    {id: 2, name: "PHONEUKCISCO25.53", hwTypeId: 3, cost: 1000}
]


export const hardwareRoute = new Hono()
    .get("/",(c) => c.json(fakeHardwares))
    .post('/', zValidator("json",createPostSchema), async(c) => {
        try {
            const hardware = c.req.valid("json");
            const nextHardwareId = (fakeHardwares.at(-1)?.id ?? 0) + 1;
            const newHardware: Hardware = {
                id: nextHardwareId,
                name: hardware.name,
                cost: hardware.cost,
                hwTypeId: hardware.hwTypeId,
            };

            fakeHardwares.push(newHardware);
            return c.json(newHardware, 201);
        } catch(err) {
            if (err instanceof z.ZodError) {
                return c.json({ errors: err.flatten() }, 400);
            } else {
                throw err;
            }
        }
    })
    .get("/:id{[0-9]+}", (c)=> {
        const id = Number.parseInt(c.req.param("id"));
        const item = fakeHardwares.find(item => item.id === id);
        if (!item){
            return c.notFound()
        }
        return c.json(item);
    })
    .delete("/:id{[0-9]+}", (c)=> {
        const id = Number.parseInt(c.req.param("id"));
        const item = fakeHardwares.findIndex(item => item.id === id);
        if (item === -1){
            return c.notFound()
        }
        const deletedItem = fakeHardwares.splice(item,1)[0];
        return c.json({hardware: deletedItem});
    })
//.put